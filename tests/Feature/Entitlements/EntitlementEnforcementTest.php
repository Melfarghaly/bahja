<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TenantEntitlementOverride;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'tenant', 'entitled:auto_collection'])
        ->get('/_test/collections', fn () => 'ok');
});

it('blocks a route whose feature is not in the plan', function () {
    [, $owner] = createNurseryWithOwner();

    $this->actingAs($owner)->get('/_test/collections')->assertStatus(402);
});

it('opens the route once the feature is granted by an override', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    TenantEntitlementOverride::factory()->create(['tenant_id' => $tenant->id, 'key' => 'auto_collection', 'value' => true]);

    $this->actingAs($owner)->get('/_test/collections')->assertOk();
});

it('stops adding staff beyond the plan limit', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $plan = SubscriptionPlan::factory()->create(['slug' => 'solo', 'max_teachers' => 1]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id]);
    attachTeacher($tenant);

    $this->actingAs($owner)
        ->post(route('nursery.teachers.store'), ['name' => 'Second', 'phone' => '01000000099', 'role' => 'teacher'])
        ->assertSessionHas('error');

    expect($tenant->teachers()->count())->toBe(1);
});

it('does not count an existing staff member again', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $plan = SubscriptionPlan::factory()->create(['slug' => 'solo', 'max_teachers' => 1]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id]);
    $teacher = attachTeacher($tenant);
    $teacher->update(['phone' => '01000000077']);

    $this->actingAs($owner)
        ->post(route('nursery.teachers.store'), ['name' => $teacher->name, 'phone' => '01000000077', 'role' => 'head_teacher'])
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('error');
});
