<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;

it('lets a super admin create a plan', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.plans.store'), [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price_egp' => 5000,
            'billing_cycle' => 'monthly',
            'max_children' => 500,
            'included_sms' => 1000,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.plans.index'));

    expect(SubscriptionPlan::where('slug', 'enterprise')->exists())->toBeTrue();
});

it('refuses to delete a plan that still has subscriptions', function () {
    $admin = User::factory()->superAdmin()->create();
    $plan = SubscriptionPlan::factory()->create();
    $tenant = Tenant::factory()->create();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id]);

    $this->actingAs($admin)
        ->delete(route('admin.plans.destroy', $plan))
        ->assertSessionHas('error');

    expect(SubscriptionPlan::find($plan->id))->not->toBeNull();
});
