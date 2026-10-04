<?php

use App\Enums\RolloutFlag;
use App\Models\Tenant;
use App\Models\TenantEntitlementOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Postman');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Create a nursery (tenant) with an owner member.
 *
 * @return array{0: Tenant, 1: User}
 */
function createNurseryWithOwner(array $tenantAttributes = []): array
{
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create($tenantAttributes);

    $tenant->members()->attach($owner->id, [
        'member_type' => 'owner',
        'status' => 'active',
    ]);

    return [$tenant, $owner];
}

/**
 * Attach a user to a tenant as a teacher (both pivots).
 */
function attachTeacher(Tenant $tenant, ?User $teacher = null): User
{
    $teacher ??= User::factory()->create();

    $teacher->nurseriesAsTeacher()->attach($tenant->id, [
        'role' => 'teacher',
        'status' => 'active',
        'employment_type' => 'full_time',
        'joined_at' => now(),
    ]);

    $tenant->members()->attach($teacher->id, ['member_type' => 'teacher', 'status' => 'active']);

    return $teacher;
}

/**
 * Release Bahga Pay to a nursery (rollout flag) — tuition routes 404 otherwise.
 */
function enableBahgaPay(Tenant $tenant): void
{
    Feature::for($tenant)->activate(RolloutFlag::BahgaPay->value);
}

require_once __DIR__.'/Support/payments.php';

/**
 * Release Safe Pickup 2.0 to a nursery and grant the plan feature it needs.
 */
function enableSafePickup(Tenant $tenant): void
{
    Feature::for($tenant)->activate(RolloutFlag::SafePickupV2->value);
    TenantEntitlementOverride::factory()->create(['tenant_id' => $tenant->id, 'key' => 'pickup_passes', 'value' => true]);
}

/**
 * Release the Messaging Hub (arrival / pickup notifications) to a nursery.
 */
function enableMessagingHub(Tenant $tenant): void
{
    Feature::for($tenant)->activate(RolloutFlag::MessagingHub->value);
}
