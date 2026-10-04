<?php

use App\Enums\Addon;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\TenantEntitlementOverride;
use App\Services\EntitlementService;
use App\Support\Entitlements\PlanCatalog;
use Database\Seeders\SubscriptionPlanSeeder;

function subscribeTo(Tenant $tenant, string $slug): void
{
    (new SubscriptionPlanSeeder)->run();

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => SubscriptionPlan::where('slug', $slug)->value('id'),
    ]);
}

function entitlementsOf(Tenant $tenant)
{
    return app(EntitlementService::class)->for($tenant);
}

it('falls back to the free tier without an active subscription', function () {
    $tenant = Tenant::factory()->create();

    $entitlements = entitlementsOf($tenant);

    expect($entitlements->limit(Limit::Children))->toBe(20)
        ->and($entitlements->limit(Limit::DailyPhotosPerChild))->toBe(3)
        ->and($entitlements->allows(Feature::Attendance))->toBeTrue()
        ->and($entitlements->allows(Feature::LostChild))->toBeTrue()
        ->and($entitlements->allows(Feature::AutoCollection))->toBeFalse();
});

it('grants the features and limits of the subscribed plan', function () {
    $tenant = Tenant::factory()->create();
    subscribeTo($tenant, 'pro');

    $entitlements = entitlementsOf($tenant);

    expect($entitlements->planName)->toBe('Pro')
        ->and($entitlements->allows(Feature::AutoCollection))->toBeTrue()
        ->and($entitlements->allows(Feature::WhiteLabel))->toBeFalse()
        ->and($entitlements->limit(Limit::Children))->toBe(120)
        ->and($entitlements->isUnlimited(Limit::Staff))->toBeTrue()
        ->and($entitlements->isUnlimited(Limit::DailyPhotosPerChild))->toBeTrue();
});

it('extends limits and unlocks features with active add-ons only', function () {
    $tenant = Tenant::factory()->create();
    subscribeTo($tenant, 'basic');

    TenantAddon::factory()->create(['tenant_id' => $tenant->id, 'addon' => Addon::ExtraChildren, 'quantity' => 2]);
    TenantAddon::factory()->create(['tenant_id' => $tenant->id, 'addon' => Addon::ExtraStaff, 'quantity' => 3]);
    TenantAddon::factory()->create(['tenant_id' => $tenant->id, 'addon' => Addon::Buses]);
    TenantAddon::factory()->create([
        'tenant_id' => $tenant->id, 'addon' => Addon::ExtraChildren, 'quantity' => 50, 'ends_at' => now()->subDay(),
    ]);

    $entitlements = entitlementsOf($tenant);

    expect($entitlements->limit(Limit::Children))->toBe(70)   // 50 + 2×10, expired add-on ignored
        ->and($entitlements->limit(Limit::Staff))->toBe(11)   // 8 + 3
        ->and($entitlements->allows(Feature::Buses))->toBeTrue();
});

it('lets active overrides win over the plan', function () {
    $tenant = Tenant::factory()->create();
    subscribeTo($tenant, 'basic');

    TenantEntitlementOverride::factory()->create(['tenant_id' => $tenant->id, 'key' => 'white_label', 'value' => true]);
    TenantEntitlementOverride::factory()->create(['tenant_id' => $tenant->id, 'key' => 'messaging', 'value' => false]);
    TenantEntitlementOverride::factory()->create(['tenant_id' => $tenant->id, 'key' => 'children', 'value' => null]);
    TenantEntitlementOverride::factory()->create([
        'tenant_id' => $tenant->id, 'key' => 'public_api', 'value' => true, 'expires_at' => now()->subMinute(),
    ]);

    $entitlements = entitlementsOf($tenant);

    expect($entitlements->allows(Feature::WhiteLabel))->toBeTrue()
        ->and($entitlements->allows(Feature::Messaging))->toBeFalse()
        ->and($entitlements->isUnlimited(Limit::Children))->toBeTrue()
        ->and($entitlements->allows(Feature::PublicApi))->toBeFalse();
});

it('seeds the plans from the catalog', function () {
    (new SubscriptionPlanSeeder)->run();

    foreach (PlanCatalog::tiers() as $slug => $tier) {
        $plan = SubscriptionPlan::where('slug', $slug)->sole();

        expect($plan->price_egp)->toBe($tier['price_egp'])
            ->and($plan->max_children)->toBe($tier['max_children'])
            ->and($plan->featureList())->toEqual($tier['features']);
    }
});
