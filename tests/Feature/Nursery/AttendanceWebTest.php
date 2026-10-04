<?php

use App\Models\Child;
use App\Models\User;

it('records check-in then verified check-out for an authorized collector', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);
    $collector = User::factory()->create();
    $child->guardians()->attach($collector->id, [
        'tenant_id' => $tenant->id, 'relationship' => 'mother', 'role' => 'primary', 'can_pickup' => true,
    ]);

    $this->actingAs($owner)->post(route('nursery.attendance.check-in', $child))->assertRedirect();
    $this->actingAs($owner)->post(route('nursery.attendance.check-out', $child), ['collector_id' => $collector->id])
        ->assertRedirect();

    $attendance = $child->attendances()->whereDate('date', today())->first();
    expect($attendance->pickup_verified)->toBeTrue();
    expect($attendance->picked_up_by)->toBe($collector->id);
});

it('refuses check-out by an unauthorized collector', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);
    $stranger = User::factory()->create();
    $child->guardians()->attach($stranger->id, [
        'tenant_id' => $tenant->id, 'relationship' => 'other', 'role' => 'viewer', 'can_pickup' => false,
    ]);

    $this->actingAs($owner)->post(route('nursery.attendance.check-in', $child));
    $this->actingAs($owner)->post(route('nursery.attendance.check-out', $child), ['collector_id' => $stranger->id])
        ->assertSessionHas('error');

    expect($child->attendances()->whereDate('date', today())->first()->pickup_verified)->toBeFalse();
});
