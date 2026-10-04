<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\User;

function linkGuardian(Child $child, User $guardian, array $pivot = []): void
{
    $child->guardians()->attach($guardian->id, $pivot + [
        'tenant_id' => $child->tenant_id,
        'relationship' => 'mother',
        'role' => 'primary',
        'can_pickup' => true,
    ]);
}

it('records a verified pickup with the acting staff member', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);
    $collector = User::factory()->create();
    linkGuardian($child, $collector);

    $this->actingAs($owner)
        ->postJson('/api/v1/attendance/check-out', ['child_id' => $child->id, 'collector_id' => $collector->id])
        ->assertOk();

    $log = AuditLog::withoutGlobalScopes()->sole();

    expect($log->action)->toBe(AuditAction::PickupVerified)
        ->and($log->tenant_id)->toBe($tenant->id)
        ->and($log->actor_id)->toBe($owner->id)
        ->and($log->subject_id)->toBe($child->id)
        ->and($log->properties['collector_id'])->toBe($collector->id);
});

it('records a denied pickup attempt', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);
    $stranger = User::factory()->create();

    $this->actingAs($owner)
        ->postJson('/api/v1/attendance/check-out', ['child_id' => $child->id, 'collector_id' => $stranger->id])
        ->assertForbidden();

    expect(AuditLog::withoutGlobalScopes()->sole()->action)->toBe(AuditAction::PickupDenied);
});

it('records guardian permission changes with before and after state', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);
    $driver = User::factory()->create();

    $payload = ['user_id' => $driver->id, 'relationship' => 'driver', 'role' => 'pickup_authorized', 'can_pickup' => true];

    $this->actingAs($owner)->postJson("/api/v1/children/{$child->id}/guardians", $payload)->assertSuccessful();
    $this->actingAs($owner)->postJson("/api/v1/children/{$child->id}/guardians", ['can_pickup' => false] + $payload)->assertSuccessful();
    $this->actingAs($owner)->deleteJson("/api/v1/children/{$child->id}/guardians/{$driver->id}")->assertSuccessful();

    $logs = AuditLog::withoutGlobalScopes()->orderBy('id')->get();

    expect($logs->pluck('action')->all())->toBe([
        AuditAction::GuardianAttached,
        AuditAction::GuardianUpdated,
        AuditAction::GuardianDetached,
    ]);
    expect($logs[1]->properties['before']['can_pickup'])->toBeTrue()
        ->and($logs[1]->properties['after']['can_pickup'])->toBeFalse()
        ->and($logs[2]->properties['guardian_id'])->toBe($driver->id);
});

it('refuses to modify or delete an audit entry', function () {
    [$tenant] = createNurseryWithOwner();
    $log = AuditLog::create(['tenant_id' => $tenant->id, 'action' => AuditAction::PickupDenied]);

    expect(fn () => $log->update(['action' => AuditAction::PickupVerified]))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});
