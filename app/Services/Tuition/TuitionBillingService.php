<?php

namespace App\Services\Tuition;

use App\Enums\AuditAction;
use App\Enums\ChildStatus;
use App\Enums\CustodyFlag;
use App\Enums\DiscountType;
use App\Enums\GuardianRole;
use App\Enums\InvoiceItemKind;
use App\Enums\TuitionInvoiceStatus;
use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Models\FeeDiscount;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Support\Money;
use App\Support\Tuition\BillingRunResult;
use App\Support\Tuition\ShareAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates a period's family invoices: one invoice per paying guardian per
 * month, covering every child they pay for. Safe to run repeatedly — a payer
 * already invoiced for the period is skipped (enforced by a unique index).
 *
 * Discount rules, in order, per fee line:
 *   1. the child's own discount (staff / scholarship / custom) on the fee;
 *   2. the nursery's sibling discount on what remains, for the 2nd and later
 *      child of the same payer (children ordered by enrollment, i.e. id).
 */
class TuitionBillingService
{
    public const DEFAULT_DUE_DAY = 5;

    public function __construct(
        private LedgerService $ledger,
        private DocumentNumberService $numbers,
        private AuditLogger $audit,
    ) {}

    public function generate(Tenant $tenant, CarbonImmutable $period, ?User $actor = null): BillingRunResult
    {
        $period = $period->startOfMonth();

        $byChild = $this->dueAssignments($tenant, $period)->groupBy('child_id');
        $siblingDiscount = FeeDiscount::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', DiscountType::Sibling->value)
            ->where('is_active', true)
            ->first();

        $families = [];
        $unbillable = [];
        $shareWarnings = [];

        foreach ($byChild as $assignments) {
            /** @var Child $child */
            $child = $assignments->first()->child;
            $childName = trim($child->first_name.' '.$child->last_name);
            [$shares, $sharesValid] = $this->payerShares($child);

            if ($shares === []) {
                $unbillable[] = $childName;

                continue;
            }

            if (! $sharesValid) {
                $shareWarnings[] = $childName;
            }

            foreach (array_keys($shares) as $payerId) {
                $families[$payerId][$child->id] = ['assignments' => $assignments, 'shares' => $shares];
            }
        }

        $created = 0;
        $alreadyInvoiced = 0;
        $billed = 0;

        foreach ($families as $payerId => $children) {
            ksort($children);

            $invoice = $this->issueFamilyInvoice($tenant, $period, $payerId, array_values($children), $siblingDiscount);

            if ($invoice === null) {
                $alreadyInvoiced++;

                continue;
            }

            $created++;
            $billed += $invoice->total_piasters;
        }

        $result = new BillingRunResult($created, $alreadyInvoiced, $unbillable, $billed, $shareWarnings);

        if ($created > 0 || $unbillable !== []) {
            $this->audit->record(AuditAction::TuitionInvoicesGenerated, $tenant, [
                'period' => $period->format('Y-m'),
                'created' => $created,
                'already_invoiced' => $alreadyInvoiced,
                'unbillable_children' => $unbillable,
                'share_warnings' => $shareWarnings,
                'total_piasters' => $billed,
            ], $actor, $tenant->id);
        }

        return $result;
    }

    /**
     * Fee enrollments billable in the period, for active children and active plans.
     *
     * @return Collection<int, ChildFeePlan>
     */
    private function dueAssignments(Tenant $tenant, CarbonImmutable $period): Collection
    {
        return ChildFeePlan::withoutGlobalScopes()
            ->where('child_fee_plans.tenant_id', $tenant->id)
            ->covering($period)
            ->whereHas('feePlan', fn ($q) => $q->withoutGlobalScopes()->where('is_active', true))
            ->whereHas('child', fn ($q) => $q->withoutGlobalScopes()->where('status', ChildStatus::Active->value))
            ->with([
                'feePlan' => fn ($q) => $q->withoutGlobalScopes(),
                'discount' => fn ($q) => $q->withoutGlobalScopes(),
                'child' => fn ($q) => $q->withoutGlobalScopes()->with('guardians'),
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (ChildFeePlan $a) => $a->feePlan->frequency->isDueIn($a->starts_on, $period))
            ->values();
    }

    /**
     * Who pays for the child and in which proportion (basis points by user id).
     * Payers are the guardians flagged is_payer, else the primary guardian;
     * custody-blocked guardians never qualify. Several payers use their
     * billing shares when those add up to 100%, otherwise an equal split
     * (and the second value is false so the run can warn about it).
     *
     * @return array{0: array<int, int>, 1: bool}
     */
    private function payerShares(Child $child): array
    {
        $eligible = $child->guardians
            ->reject(fn (User $g) => $g->pivot->custody_flag === CustodyFlag::Blocked->value)
            ->sortBy('id');

        $payers = $eligible->filter(fn (User $g) => (bool) $g->pivot->is_payer)->values();

        if ($payers->isEmpty()) {
            $primary = $eligible->first(fn (User $g) => $g->pivot->role === GuardianRole::Primary->value);

            return [$primary === null ? [] : [$primary->id => 10_000], true];
        }

        if ($payers->count() === 1) {
            return [[$payers->first()->id => 10_000], true];
        }

        $declared = $payers->mapWithKeys(fn (User $g) => [$g->id => (int) $g->pivot->billing_share_bp])->all();

        if (array_sum($declared) === 10_000 && min($declared) > 0) {
            ksort($declared);

            return [$declared, true];
        }

        return [ShareAllocator::equal(array_keys($declared)), false];
    }

    /**
     * @param  array<int, array{assignments: Collection<int, ChildFeePlan>, shares: array<int, int>}>  $children  in sibling order
     */
    private function issueFamilyInvoice(Tenant $tenant, CarbonImmutable $period, int $payerId, array $children, ?FeeDiscount $siblingDiscount): ?TuitionInvoice
    {
        $billingKey = TuitionInvoice::billingKey($payerId, $period);

        $exists = TuitionInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('billing_key', $billingKey)
            ->exists();

        if ($exists) {
            return null;
        }

        $lines = $this->buildLines($children, $payerId, $siblingDiscount);
        $subtotal = array_sum(array_map(fn ($l) => max(0, $l['amount_piasters']), $lines));
        $discount = -array_sum(array_map(fn ($l) => min(0, $l['amount_piasters']), $lines));
        $total = $subtotal - $discount;

        try {
            return DB::transaction(function () use ($tenant, $period, $payerId, $billingKey, $lines, $subtotal, $discount, $total) {
                $invoice = TuitionInvoice::create([
                    'tenant_id' => $tenant->id,
                    'number' => $this->numbers->next($tenant, DocumentNumberService::INVOICE),
                    'payer_id' => $payerId,
                    'period_start' => $period,
                    'issued_on' => today(),
                    'due_on' => $this->dueDate($tenant, $period),
                    'subtotal_piasters' => $subtotal,
                    'discount_piasters' => $discount,
                    'total_piasters' => $total,
                    'paid_piasters' => 0,
                    // A fully discounted invoice (100% scholarship) has nothing to collect.
                    'status' => $total === 0 ? TuitionInvoiceStatus::Paid : TuitionInvoiceStatus::Open,
                    'billing_key' => $billingKey,
                ]);

                foreach ($lines as $line) {
                    $invoice->items()->create(['tenant_id' => $tenant->id] + $line);
                }

                $this->ledger->postInvoiceIssued($invoice);

                return $invoice;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent run invoiced this payer first.
            return null;
        }
    }

    /**
     * Invoice lines for one payer. Every amount is computed on the child's
     * full fee first, then the payer's part is taken with an exact allocation,
     * so split families together pay exactly what one family would.
     *
     * @param  array<int, array{assignments: Collection<int, ChildFeePlan>, shares: array<int, int>}>  $children
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(array $children, int $payerId, ?FeeDiscount $siblingDiscount): array
    {
        $lines = [];

        foreach ($children as $siblingIndex => ['assignments' => $assignments, 'shares' => $shares]) {
            $split = count($shares) > 1;
            $suffix = $split ? ' ('.rtrim(rtrim(number_format($shares[$payerId] / 100, 2), '0'), '.').'%)' : '';
            $part = fn (int $amount) => ShareAllocator::allocate($amount, $shares)[$payerId];

            foreach ($assignments as $assignment) {
                $child = $assignment->child;
                $plan = $assignment->feePlan;
                $childName = $child->first_name.$suffix;
                $fee = $plan->amount();

                $lines[] = [
                    'child_id' => $child->id,
                    'fee_plan_id' => $plan->id,
                    'kind' => InvoiceItemKind::Fee,
                    'description' => "{$plan->name} — {$childName}",
                    'amount_piasters' => $part($fee->piasters),
                ];

                $remaining = $fee;

                if ($assignment->discount !== null && $assignment->discount->is_active) {
                    $off = $assignment->discount->discountOn($fee);
                    $lines[] = $this->discountLine($child->id, $assignment->discount, Money::of($part($off->piasters)), $childName);
                    $remaining = $fee->minus($off);
                }

                if ($siblingIndex > 0 && $siblingDiscount !== null && $remaining->isPositive()) {
                    $off = $siblingDiscount->discountOn($remaining);
                    $lines[] = $this->discountLine($child->id, $siblingDiscount, Money::of($part($off->piasters)), $childName);
                }
            }
        }

        return array_values(array_filter($lines, fn ($l) => $l['amount_piasters'] !== 0));
    }

    /**
     * @return array<string, mixed>
     */
    private function discountLine(int $childId, FeeDiscount $discount, Money $amount, string $childName): array
    {
        return [
            'child_id' => $childId,
            'fee_discount_id' => $discount->id,
            'kind' => InvoiceItemKind::Discount,
            'description' => "{$discount->name} — {$childName}",
            'amount_piasters' => -$amount->piasters,
        ];
    }

    private function dueDate(Tenant $tenant, CarbonImmutable $period): CarbonImmutable
    {
        $dueDay = (int) ($tenant->settings['tuition_due_day'] ?? self::DEFAULT_DUE_DAY);
        $due = $period->addDays(max(1, min(28, $dueDay)) - 1);

        // Never issue an invoice that is already overdue.
        return $due->isBefore(today()) ? CarbonImmutable::today() : $due;
    }
}
