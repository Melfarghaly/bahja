<?php

namespace App\Services\Tuition;

use App\Enums\TuitionInvoiceStatus;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\TuitionPayment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Collections KPIs for a nursery: what was billed and collected in a month,
 * what is still owed, how late it is (aging) and which families owe most.
 * All aggregation is done in SQL with date boundaries computed here, so the
 * same queries run on SQLite and PostgreSQL.
 */
class CollectionsReportService
{
    /**
     * Aging buckets by days past due: [label, min days, max days|null].
     *
     * @var array<int, array{0: string, 1: int, 2: ?int}>
     */
    public const AGING_BUCKETS = [
        ['لم يحن موعدها', -PHP_INT_MAX, 0],
        ['1–30 يوم', 1, 30],
        ['31–60 يوم', 31, 60],
        ['61–90 يوم', 61, 90],
        ['أكثر من 90 يوم', 91, null],
    ];

    /**
     * @return array{billed: Money, collected: Money, collectedForPeriod: Money, collectionRate: ?int, outstanding: Money, overdue: Money, overdueInvoices: int}
     */
    public function summary(Tenant $tenant, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $today = CarbonImmutable::today()->toDateString();

        $billed = (int) $this->invoices($tenant)
            ->where('status', '!=', TuitionInvoiceStatus::Void->value)
            ->whereDate('period_start', $start->toDateString())
            ->sum('total_piasters');

        $collectedForPeriod = (int) $this->invoices($tenant)
            ->where('status', '!=', TuitionInvoiceStatus::Void->value)
            ->whereDate('period_start', $start->toDateString())
            ->sum('paid_piasters');

        $collected = (int) TuitionPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('voided_at')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount_piasters');

        $open = $this->invoices($tenant)->collectible()
            ->selectRaw('coalesce(sum(total_piasters - paid_piasters), 0) as outstanding')
            ->selectRaw('coalesce(sum(case when due_on < ? then total_piasters - paid_piasters else 0 end), 0) as overdue', [$today])
            ->selectRaw('sum(case when due_on < ? then 1 else 0 end) as overdue_count', [$today])
            ->first();

        return [
            'billed' => Money::of($billed),
            'collected' => Money::of($collected),
            'collectedForPeriod' => Money::of($collectedForPeriod),
            // Share of the month's bills already paid; null when nothing was billed.
            'collectionRate' => $billed > 0 ? intdiv($collectedForPeriod * 100, $billed) : null,
            'outstanding' => Money::of((int) $open->outstanding),
            'overdue' => Money::of((int) $open->overdue),
            'overdueInvoices' => (int) $open->overdue_count,
        ];
    }

    /**
     * Outstanding balance per aging bucket.
     *
     * @return array<int, array{label: string, amount: Money, invoices: int}>
     */
    public function aging(Tenant $tenant): array
    {
        $today = CarbonImmutable::today();
        $query = $this->invoices($tenant)->collectible();

        foreach (self::AGING_BUCKETS as $i => [, $minDays, $maxDays]) {
            // days past due in [min, max]  ⇔  due_on in [today − max, today − min]
            $conditions = [];
            $bindings = [];

            if ($maxDays !== null) {
                $conditions[] = 'due_on >= ?';
                $bindings[] = $today->subDays($maxDays)->toDateString();
            }
            if ($minDays !== -PHP_INT_MAX) {
                $conditions[] = 'due_on <= ?';
                $bindings[] = $today->subDays($minDays)->toDateString();
            }

            $when = implode(' and ', $conditions);
            $query->selectRaw("coalesce(sum(case when {$when} then total_piasters - paid_piasters else 0 end), 0) as amount_{$i}", $bindings)
                ->selectRaw("sum(case when {$when} then 1 else 0 end) as count_{$i}", $bindings);
        }

        $row = $query->first();

        return array_map(fn (int $i) => [
            'label' => self::AGING_BUCKETS[$i][0],
            'amount' => Money::of((int) $row->{"amount_{$i}"}),
            'invoices' => (int) $row->{"count_{$i}"},
        ], array_keys(self::AGING_BUCKETS));
    }

    /**
     * Families with the largest overdue balance.
     *
     * @return Collection<int, object{payer_id: int, name: string, phone: ?string, overdue: Money, invoices: int, oldest_due_on: string}>
     */
    public function topLateFamilies(Tenant $tenant, int $limit = 10): Collection
    {
        return $this->invoices($tenant)->collectible()
            ->whereDate('due_on', '<', CarbonImmutable::today()->toDateString())
            ->join('users', 'users.id', '=', 'tuition_invoices.payer_id')
            ->groupBy('tuition_invoices.payer_id', 'users.name', 'users.phone')
            ->selectRaw('tuition_invoices.payer_id, users.name, users.phone')
            ->selectRaw('sum(total_piasters - paid_piasters) as overdue_piasters')
            ->selectRaw('count(*) as invoices, min(due_on) as oldest_due_on')
            ->orderByDesc('overdue_piasters')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($row) => (object) [
                'payer_id' => (int) $row->payer_id,
                'name' => $row->name,
                'phone' => $row->phone,
                'overdue' => Money::of((int) $row->overdue_piasters),
                'invoices' => (int) $row->invoices,
                'oldest_due_on' => CarbonImmutable::parse($row->oldest_due_on)->toDateString(),
            ]);
    }

    /**
     * The month's collections by payment method.
     *
     * @return array<string, Money> keyed by TuitionPaymentMethod value
     */
    public function collectedByMethod(Tenant $tenant, CarbonImmutable $month): array
    {
        return TuitionPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('voided_at')
            ->whereBetween('paid_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->groupBy('method')
            ->select('method', DB::raw('sum(amount_piasters) as total'))
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [$row->method => Money::of((int) $row->total)])
            ->all();
    }

    private function invoices(Tenant $tenant)
    {
        return TuitionInvoice::withoutGlobalScopes()->where('tuition_invoices.tenant_id', $tenant->id);
    }
}
