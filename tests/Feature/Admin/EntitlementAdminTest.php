<?php

use App\Enums\AuditAction;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\TenantEntitlementOverride;
use App\Models\User;
use App\Services\EntitlementService;

it('lets a super admin grant and revoke an override, with an audit trail', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.nurseries.overrides.store', $tenant), [
            'key' => 'white_label', 'value' => 'on', 'reason' => 'Founders deal',
        ])
        ->assertSessionHasNoErrors();

    expect(app(EntitlementService::class)->for($tenant)->allows(Feature::WhiteLabel))->toBeTrue();

    $override = TenantEntitlementOverride::withoutGlobalScopes()->sole();
    expect($override->granted_by)->toBe($admin->id);

    $this->actingAs($admin)->delete(route('admin.nurseries.overrides.destroy', [$tenant, $override]))->assertRedirect();

    expect(TenantEntitlementOverride::withoutGlobalScopes()->count())->toBe(0)
        ->and(AuditLog::withoutGlobalScopes()->pluck('action')->all())->toBe([
            AuditAction::EntitlementOverrideGranted,
            AuditAction::EntitlementOverrideRevoked,
        ]);
});

it('stores an empty limit override as unlimited', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.nurseries.overrides.store', $tenant), ['key' => 'children', 'value' => '', 'reason' => 'Chain pilot'])
        ->assertSessionHasNoErrors();

    expect(app(EntitlementService::class)->for($tenant)->isUnlimited(Limit::Children))->toBeTrue();
});

it('validates override values per key type', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.nurseries.overrides.store', $tenant), ['key' => 'white_label', 'value' => '5', 'reason' => 'x'])
        ->assertSessionHasErrors('value');

    $this->actingAs($admin)
        ->post(route('admin.nurseries.overrides.store', $tenant), ['key' => 'children', 'value' => 'on', 'reason' => 'x'])
        ->assertSessionHasErrors('value');
});

it('adds an add-on for a nursery', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.nurseries.addons.store', $tenant), ['addon' => 'buses', 'quantity' => 1])
        ->assertSessionHasNoErrors();

    expect(app(EntitlementService::class)->for($tenant)->allows(Feature::Buses))->toBeTrue();
});

it('refuses to remove an add-on through another nursery\'s URL', function () {
    $admin = User::factory()->superAdmin()->create();
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
    $addon = TenantAddon::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($admin)
        ->delete(route('admin.nurseries.addons.destroy', [$tenantA, $addon]))
        ->assertNotFound();

    expect(TenantAddon::withoutGlobalScopes()->count())->toBe(1);
});

it('forbids non super admins from changing entitlements', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $this->actingAs($owner)
        ->post(route('admin.nurseries.overrides.store', $tenant), ['key' => 'white_label', 'value' => 'on', 'reason' => 'x'])
        ->assertForbidden();
});

it('renders the entitlements panel on the nursery page', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.nurseries.show', $tenant))
        ->assertOk()
        ->assertSee('الاستحقاقات الفعلية')
        ->assertSee(Feature::LostChild->label());
});

it('keeps plan features when a plan is edited', function () {
    $admin = User::factory()->superAdmin()->create();
    $plan = SubscriptionPlan::factory()->create(['features' => ['attendance']]);

    $this->actingAs($admin)
        ->put(route('admin.plans.update', $plan), [
            'name' => $plan->name, 'slug' => $plan->slug, 'price_egp' => 100, 'billing_cycle' => 'monthly',
            'included_sms' => 0, 'is_active' => 1, 'features' => ['attendance', 'messaging'],
        ])
        ->assertSessionHasNoErrors();

    expect($plan->fresh()->features)->toBe(['attendance', 'messaging']);
});
