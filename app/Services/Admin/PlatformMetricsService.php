<?php

namespace App\Services\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Child;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide metrics for the super admin dashboard. These queries are NOT
 * tenant-scoped: the super admin sees the whole platform.
 */
class PlatformMetricsService
{
    private const ACTIVE_STATUSES = [
        SubscriptionStatus::Trialing->value,
        SubscriptionStatus::Active->value,
        SubscriptionStatus::PastDue->value,
    ];

    /**
     * @return array<string, int|float>
     */
    public function kpis(): array
    {
        return [
            'tenants_total' => Tenant::count(),
            'tenants_active' => Tenant::where('status', 'active')->count(),
            'subscriptions_active' => Subscription::whereIn('status', self::ACTIVE_STATUSES)->count(),
            'children_total' => Child::count(),
            'users_total' => User::count(),
            'mrr_egp' => $this->monthlyRecurringRevenue(),
        ];
    }

    /**
     * Monthly Recurring Revenue in EGP. Yearly plans are normalized to a monthly figure.
     */
    public function monthlyRecurringRevenue(): int
    {
        $rows = Subscription::query()
            ->whereIn('subscriptions.status', self::ACTIVE_STATUSES)
            ->join('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.subscription_plan_id')
            ->select('subscription_plans.price_egp', 'subscription_plans.billing_cycle')
            ->get();

        $mrr = 0;

        foreach ($rows as $row) {
            $mrr += $row->billing_cycle === 'yearly'
                ? (int) round($row->price_egp / 12)
                : (int) $row->price_egp;
        }

        return $mrr;
    }

    /**
     * Count of tenants grouped by their current plan, for a simple breakdown chart.
     *
     * @return array<string, int>
     */
    public function tenantsByPlan(): array
    {
        return Subscription::query()
            ->whereIn('subscriptions.status', self::ACTIVE_STATUSES)
            ->join('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.subscription_plan_id')
            ->select('subscription_plans.name', DB::raw('count(*) as total'))
            ->groupBy('subscription_plans.name')
            ->pluck('total', 'name')
            ->toArray();
    }
}
