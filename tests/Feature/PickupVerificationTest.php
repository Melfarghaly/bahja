<?php

use App\Models\Child;
use App\Models\User;

it('allows an authorized guardian to pick up a child', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);
    $collector = User::factory()->create();

    $child->guardians()->attach($collector->id, [
        'tenant_id' => $tenant->id,
        'relationship' => 'mother',
        'role' => 'primary',
        'can_pickup' => true,
    ]);

    $this->actingAs($owner)
        ->postJson('/api/v1/attendance/check-out', [
            'child_id' => $child->id,
            'collector_id' => $collector->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.pickup_verified', true);
});

it('denies pickup by a guardian who is not authorized', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);
    $collector = User::factory()->create();

    $child->guardians()->attach($collector->id, [
        'tenant_id' => $tenant->id,
        'relationship' => 'grandparent',
        'role' => 'viewer',
        'can_pickup' => false,
    ]);

    $this->actingAs($owner)
        ->postJson('/api/v1/attendance/check-out', [
            'child_id' => $child->id,
            'collector_id' => $collector->id,
        ])
        ->assertForbidden();
});

it('denies pickup by a custody-blocked guardian even if can_pickup is true', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);
    $collector = User::factory()->create();

    $child->guardians()->attach($collector->id, [
        'tenant_id' => $tenant->id,
        'relationship' => 'father',
        'role' => 'pickup_authorized',
        'can_pickup' => true,
        'custody_flag' => 'blocked',
    ]);

    $this->actingAs($owner)
        ->postJson('/api/v1/attendance/check-out', [
            'child_id' => $child->id,
            'collector_id' => $collector->id,
        ])
        ->assertForbidden();
});
