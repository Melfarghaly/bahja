<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Enums\RolloutFlag;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\EntitlementService;
use App\Services\Pickup\LatePickupService;
use App\Services\RolloutService;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Every 15 minutes: alert guardians, then managers, about children still at
 * the nursery after its pickup deadline.
 */
class SendLatePickupAlerts extends Command
{
    protected $signature = 'pickup:late-alerts {--tenant=* : Limit to these nursery IDs}';

    protected $description = 'Alert guardians and managers about children not picked up on time';

    public function handle(LatePickupService $late, EntitlementService $entitlements, RolloutService $rollouts, TenantContext $context): int
    {
        $failures = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Active->value, TenantStatus::Trial->value])
            ->when($this->option('tenant'), fn ($q, $ids) => $q->whereKey($ids))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($late, $entitlements, $rollouts, $context, &$failures) {
                if (! $rollouts->active($tenant, RolloutFlag::SafePickupV2)
                    || ! $entitlements->for($tenant)->allows(Feature::PickupPasses)
                    || $late->deadline($tenant) === null) {
                    return;
                }

                $context->set($tenant);

                try {
                    $sent = $late->run($tenant);
                    if (array_sum($sent) > 0) {
                        $this->line("[{$tenant->id}] {$tenant->name}: {$sent['guardians']} guardian SMS, {$sent['managers']} manager SMS");
                    }
                } catch (Throwable $e) {
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
