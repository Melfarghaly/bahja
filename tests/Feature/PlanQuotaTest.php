<?php

use App\Models\Child;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

it('blocks enrolling a child beyond the plan limit', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $plan = SubscriptionPlan::factory()->create(['slug' => 'tiny', 'max_children' => 1]);
    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
    ]);

    // First child fills the quota.
    Child::factory()->create(['tenant_id' => $tenant->id]);

    $guardian = User::factory()->create();

    $this->actingAs($owner)
        ->postJson('/api/v1/children', [
            'first_name' => 'Over',
            'last_name' => 'Limit',
            'birth_date' => '2023-01-01',
            'gender' => 'female',
            'guardians' => [
                ['user_id' => $guardian->id, 'relationship' => 'mother', 'role' => 'primary'],
            ],
        ])
        ->assertStatus(402); // Payment Required -> plan limit reached.
});

it('allows unlimited children on a plan with no cap', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $plan = SubscriptionPlan::factory()->create(['slug' => 'unlimited', 'max_children' => null]);
    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
    ]);

    Child::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    $guardian = User::factory()->create();

    $this->actingAs($owner)
        ->postJson('/api/v1/children', [
            'first_name' => 'New',
            'last_name' => 'Child',
            'birth_date' => '2023-02-02',
            'gender' => 'male',
            'guardians' => [
                ['user_id' => $guardian->id, 'relationship' => 'father', 'role' => 'primary'],
            ],
        ])
        ->assertCreated();
});
