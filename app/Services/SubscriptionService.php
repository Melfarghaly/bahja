<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Exceptions\PlanLimitException;
use Illuminate\Support\Facades\DB;

/**
 * Owns subscription lifecycle and plan-quota enforcement.
 */
class SubscriptionService
{
    /**
     * Start a tenant on a plan with a trial window.
     */
    public function startTrial(Tenant $tenant, SubscriptionPlan $plan, int $trialDays = 14): Subscription
    {
        return Subscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays($trialDays),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays($trialDays),
        ]);
    }

    /**
     * Move a tenant to a different plan, enforcing downgrade limits.
     */
    public function changePlan(Tenant $tenant, SubscriptionPlan $plan): Subscription
    {
        return DB::transaction(function () use ($tenant, $plan) {
            $this->assertWithinChildLimit($tenant, $plan);

            $subscription = $tenant->activeSubscription()->firstOrFail();
            $subscription->update(['subscription_plan_id' => $plan->id]);

            return $subscription->fresh('plan');
        });
    }

    /**
     * Guard used before enrolling a child. Throws when the plan cap is reached.
     */
    public function assertCanAddChild(Tenant $tenant): void
    {
        $plan = $this->currentPlan($tenant);

        if ($plan === null || $plan->isUnlimitedChildren()) {
            return;
        }

        if ($tenant->children()->count() >= $plan->max_children) {
            throw new PlanLimitException('children', $plan->max_children);
        }
    }

    private function assertWithinChildLimit(Tenant $tenant, SubscriptionPlan $plan): void
    {
        if ($plan->max_children !== null && $tenant->children()->count() > $plan->max_children) {
            throw new PlanLimitException('children', $plan->max_children);
        }
    }

    private function currentPlan(Tenant $tenant): ?SubscriptionPlan
    {
        return $tenant->activeSubscription()->with('plan')->first()?->plan;
    }
}
