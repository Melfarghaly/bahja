<?php

namespace App\Services\Tuition;

use App\Enums\AuditAction;
use App\Enums\DunningStep;
use App\Enums\Feature;
use App\Models\DunningNotice;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Services\AuditLogger;
use App\Services\EntitlementService;
use App\Services\Messaging\SmsGateway;
use App\Services\Payments\Checkout\CheckoutGatewayRegistry;
use App\Support\Tuition\DunningRunResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Daily payment reminders for unpaid tuition invoices (the dunning ladder,
 * see DunningStep). Each invoice gets each step at most once — the notice
 * row is reserved *before* sending, under a unique (invoice, step) key.
 * After 14 days the invoice is escalated to the nursery instead of the parent.
 */
class DunningService
{
    public function __construct(
        private SmsGateway $sms,
        private PayLinkService $links,
        private EntitlementService $entitlements,
        private CheckoutGatewayRegistry $gateways,
        private AuditLogger $audit,
    ) {}

    public function run(Tenant $tenant, ?CarbonImmutable $today = null): DunningRunResult
    {
        $today ??= CarbonImmutable::today();
        $sent = $skipped = $failed = $escalated = 0;
        $canPayOnline = $this->entitlements->for($tenant)->allows(Feature::AutoCollection)
            && $this->gateways->available($tenant) !== [];

        TuitionInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->collectible()
            ->whereDate('due_on', '<=', $today->addDays(-DunningStep::BeforeDue->offsetDays())->toDateString())
            ->with('payer:id,name,phone')
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use ($tenant, $today, $canPayOnline, &$sent, &$skipped, &$failed, &$escalated) {
                foreach ($invoices as $invoice) {
                    $step = DunningStep::forDaysPastDue((int) $invoice->due_on->diffInDays($today, false));

                    if ($step === null || ! $invoice->balance()->isPositive() || ! $this->reserve($invoice, $step)) {
                        continue;
                    }

                    $outcome = $step === DunningStep::Escalated
                        ? $this->escalate($invoice)
                        : $this->remind($tenant, $invoice, $step, $today, $canPayOnline);

                    match ($outcome) {
                        DunningNotice::SENT => $step === DunningStep::Escalated ? $escalated++ : $sent++,
                        DunningNotice::SKIPPED => $skipped++,
                        default => $failed++,
                    };
                }
            });

        return new DunningRunResult($sent, $skipped, $failed, $escalated);
    }

    /**
     * Claim (invoice, step). Returns false when it was already handled.
     */
    private function reserve(TuitionInvoice $invoice, DunningStep $step): bool
    {
        return DunningNotice::query()->insertOrIgnore([
            'tenant_id' => $invoice->tenant_id,
            'tuition_invoice_id' => $invoice->id,
            'step' => $step->value,
            'channel' => $step === DunningStep::Escalated ? 'escalation' : 'sms',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    private function remind(Tenant $tenant, TuitionInvoice $invoice, DunningStep $step, CarbonImmutable $today, bool $canPayOnline): string
    {
        $phone = $invoice->payer?->phone;

        if (blank($phone)) {
            return $this->finish($invoice, $step, DunningNotice::SKIPPED, skipReason: 'no_phone');
        }

        if ($this->optedOut($invoice)) {
            return $this->finish($invoice, $step, DunningNotice::SKIPPED, phone: $phone, skipReason: 'opted_out');
        }

        $message = $this->message($tenant, $invoice, $step, $today, $canPayOnline);

        try {
            $result = $this->sms->send($phone, $message);
        } catch (Throwable $e) {
            report($e);

            return $this->finish($invoice, $step, DunningNotice::FAILED, $phone, $message, skipReason: mb_substr($e->getMessage(), 0, 255));
        }

        return $this->finish(
            $invoice, $step,
            $result->successful ? DunningNotice::SENT : DunningNotice::FAILED,
            $phone, $message, $result->providerReference,
            $result->successful ? null : mb_substr((string) $result->error, 0, 255),
        );
    }

    private function escalate(TuitionInvoice $invoice): string
    {
        $this->audit->record(AuditAction::TuitionInvoiceEscalated, $invoice, [
            'invoice' => $invoice->number,
            'balance_piasters' => $invoice->balance()->piasters,
            'days_overdue' => $invoice->daysOverdue(),
        ], tenantId: $invoice->tenant_id);

        return $this->finish($invoice, DunningStep::Escalated, DunningNotice::SENT);
    }

    /**
     * Guardians can turn SMS off per child link (child_guardian.notify_preferences).
     */
    private function optedOut(TuitionInvoice $invoice): bool
    {
        return DB::table('child_guardian')
            ->where('tenant_id', $invoice->tenant_id)
            ->where('guardian_id', $invoice->payer_id)
            ->pluck('notify_preferences')
            ->contains(fn ($prefs) => (json_decode((string) $prefs, true)['sms'] ?? true) === false);
    }

    private function message(Tenant $tenant, TuitionInvoice $invoice, DunningStep $step, CarbonImmutable $today, bool $canPayOnline): string
    {
        $amount = $invoice->balance()->format();
        $month = $invoice->period_start->format('m/Y');

        $body = match ($step) {
            DunningStep::BeforeDue => "تذكير: مصروفات شهر {$month} بقيمة {$amount} تستحق يوم {$invoice->due_on->format('d/m')}.",
            DunningStep::DueDay => "مصروفات شهر {$month} بقيمة {$amount} تستحق اليوم.",
            default => "مصروفات شهر {$month} بقيمة {$amount} متأخرة منذ ".(int) $invoice->due_on->diffInDays($today).' أيام.',
        };

        $action = $canPayOnline
            ? 'للدفع: '.$this->links->linkFor($invoice)
            : 'يرجى السداد لدى الحضانة.';

        return "{$tenant->name}: {$body} {$action}";
    }

    private function finish(
        TuitionInvoice $invoice,
        DunningStep $step,
        string $status,
        ?string $phone = null,
        ?string $message = null,
        ?string $providerReference = null,
        ?string $skipReason = null,
    ): string {
        DunningNotice::query()
            ->where('tuition_invoice_id', $invoice->id)
            ->where('step', $step->value)
            ->update([
                'status' => $status,
                'recipient_phone' => $phone,
                'message' => $message,
                'provider_reference' => $providerReference,
                'skip_reason' => $skipReason,
                'updated_at' => now(),
            ]);

        return $status;
    }
}
