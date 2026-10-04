<?php

use App\Models\Child;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\PlatformMetricsService;

it('aggregates platform-wide KPIs across all tenants', function () {
    $tenantA = Tenant::factory()->create(['status' => 'active']);
    $tenantB = Tenant::factory()->create(['status' => 'active']);

    Child::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
    Child::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    $plan = SubscriptionPlan::factory()->create(['price_egp' => 1900, 'billing_cycle' => 'monthly']);
    Subscription::factory()->create(['tenant_id' => $tenantA->id, 'subscription_plan_id' => $plan->id, 'status' => 'active']);
    Subscription::factory()->create(['tenant_id' => $tenantB->id, 'subscription_plan_id' => $plan->id, 'status' => 'active']);

    $kpis = app(PlatformMetricsService::class)->kpis();

    expect($kpis['tenants_total'])->toBe(2);
    expect($kpis['children_total'])->toBe(5);
    expect($kpis['subscriptions_active'])->toBe(2);
    expect($kpis['mrr_egp'])->toBe(3800); // 2 x 1900 monthly
});

it('normalizes yearly plans to a monthly MRR figure', function () {
    $tenant = Tenant::factory()->create();
    $plan = SubscriptionPlan::factory()->create(['price_egp' => 12000, 'billing_cycle' => 'yearly']);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id, 'status' => 'active']);

    expect(app(PlatformMetricsService::class)->monthlyRecurringRevenue())->toBe(1000); // 12000 / 12
});
