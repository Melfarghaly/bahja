<?php

use App\Models\Child;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

it('enrolls a child and links guardians with their per-pair permissions', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $plan = SubscriptionPlan::factory()->create(['slug' => 'pro', 'max_children' => 100]);
    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $mother = User::factory()->create();
    $driver = User::factory()->create();

    $payload = [
        'first_name' => 'Yousef',
        'last_name' => 'Hassan',
        'birth_date' => '2022-05-01',
        'gender' => 'male',
        'guardians' => [
            ['user_id' => $mother->id, 'relationship' => 'mother', 'role' => 'primary', 'can_pickup' => true, 'is_payer' => true],
            ['user_id' => $driver->id, 'relationship' => 'driver', 'role' => 'pickup_authorized', 'can_pickup' => true],
        ],
    ];

    $response = $this->actingAs($owner)->postJson('/api/v1/children', $payload);

    $response->assertCreated();

    $child = Child::firstWhere('first_name', 'Yousef');
    expect($child->tenant_id)->toBe($tenant->id);
    expect($child->guardians)->toHaveCount(2);
    expect($child->authorizedPickups()->count())->toBe(2);
});
