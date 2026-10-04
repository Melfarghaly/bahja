<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Enums\RolloutFlag;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\EntitlementService;
use App\Services\RolloutService;
use App\Services\Tuition\TuitionBillingService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Monthly automatic billing. Runs per nursery inside its own tenant context
 * (so the TenantScope and Row-Level Security both apply), and only for
 * nurseries that have Bahga Pay released and Auto Collection in their plan.
 */
class GenerateTuitionInvoices extends Command
{
    protected $signature = 'tuition:generate
        {--period= : Month to bill, YYYY-MM (default: current month)}
        {--tenant=* : Limit to these nursery IDs}';

    protected $description = 'Generate family tuition invoices for the period';

    public function handle(
        TuitionBillingService $billing,
        EntitlementService $entitlements,
        RolloutService $rollouts,
        TenantContext $context,
    ): int {
        $option = $this->option('period');

        if ($option !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $option)) {
            $this->error('Invalid --period, expected YYYY-MM.');

            return self::INVALID;
        }

        $period = $option !== null
            ? CarbonImmutable::createFromFormat('!Y-m', $option)
            : CarbonImmutable::now()->startOfMonth();

        $failures = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Active->value, TenantStatus::Trial->value])
            ->when($this->option('tenant'), fn ($q, $ids) => $q->whereKey($ids))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($billing, $entitlements, $rollouts, $context, $period, &$failures) {
                if (! $rollouts->active($tenant, RolloutFlag::BahgaPay)
                    || ! $entitlements->for($tenant)->allows(Feature::AutoCollection)) {
                    return;
                }

                $context->set($tenant);

                try {
                    $result = $billing->generate($tenant, $period);
                    $this->line(sprintf(
                        '[%d] %s: %d created, %d already invoiced, %d without payer, %s',
                        $tenant->id, $tenant->name, $result->created, $result->alreadyInvoiced,
                        count($result->unbillableChildren), $result->totalBilled()->format(),
                    ));
                } catch (Throwable $e) {
                    // One nursery's failure must not stop the others' billing.
                    $failures++;
                    report($e);
                    $this->error("[{$tenant->id}] {$tenant->name}: {$e->getMessage()}");
                } finally {
                    $context->forget();
                }
            });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
