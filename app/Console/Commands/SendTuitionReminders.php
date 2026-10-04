<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Enums\RolloutFlag;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\EntitlementService;
use App\Services\RolloutService;
use App\Services\Tuition\DunningService;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Daily payment reminders, per nursery, for nurseries with Bahga Pay released
 * and Auto Collection (reminders are part of it) in their plan.
 */
class SendTuitionReminders extends Command
{
    protected $signature = 'tuition:remind {--tenant=* : Limit to these nursery IDs}';

    protected $description = 'Send due / overdue tuition reminders and escalate long-overdue invoices';

    public function handle(DunningService $dunning, EntitlementService $entitlements, RolloutService $rollouts, TenantContext $context): int
    {
        $failures = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Active->value, TenantStatus::Trial->value])
            ->when($this->option('tenant'), fn ($q, $ids) => $q->whereKey($ids))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($dunning, $entitlements, $rollouts, $context, &$failures) {
                if (! $rollouts->active($tenant, RolloutFlag::BahgaPay)
                    || ! $entitlements->for($tenant)->allows(Feature::AutoCollection)) {
                    return;
                }

                $context->set($tenant);

                try {
                    $r = $dunning->run($tenant);
                    $this->line("[{$tenant->id}] {$tenant->name}: {$r->sent} sent, {$r->skipped} skipped, {$r->failed} failed, {$r->escalated} escalated");
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
