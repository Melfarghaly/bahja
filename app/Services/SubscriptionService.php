<?php

namespace App\Services;

use App\Enums\Limit;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Exceptions\PlanLimitException;
use Illuminate\Support\Facades\DB;

/**
 * Owns subscription lifecycle and plan-quota enforcement. Quotas themselves
 * are resolved by the EntitlementService (plan + add-ons + overrides).
 */
class SubscriptionService
{
    public function __construct(private EntitlementService $entitlements) {}

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

            $this->entitlements->forget($tenant);

            return $subscription->fresh('plan');
        });
    }

    /**
     * Guard used before enrolling a child. Throws when the quota is reached.
     *
     * @throws PlanLimitException
     */
    public function assertCanAddChild(Tenant $tenant): void
    {
        $this->assertCanAdd($tenant, Limit::Children);
    }

    /**
     * Guard used before adding a staff member. Throws when the quota is reached.
     *
     * @throws PlanLimitException
     */
    public function assertCanAddStaff(Tenant $tenant): void
    {
        $this->assertCanAdd($tenant, Limit::Staff);
    }

    /**
     * @throws PlanLimitException
     */
    public function assertCanAdd(Tenant $tenant, Limit $limit): void
    {
        $this->entitlements->for($tenant)->assertCanAdd($limit, $this->entitlements->usage($tenant, $limit));
    }

    private function assertWithinChildLimit(Tenant $tenant, SubscriptionPlan $plan): void
    {
        if ($plan->max_children !== null && $tenant->children()->count() > $plan->max_children) {
            throw new PlanLimitException('children', $plan->max_children);
        }
    }
}
