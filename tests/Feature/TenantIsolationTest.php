<?php

use App\Models\Child;

it('never exposes children from another tenant through the API', function () {
    [$tenantA, $ownerA] = createNurseryWithOwner();
    [$tenantB] = createNurseryWithOwner();

    Child::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
    Child::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($ownerA)->getJson('/api/v1/children');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('blocks viewing a child that belongs to another tenant', function () {
    [$tenantA, $ownerA] = createNurseryWithOwner();
    [$tenantB] = createNurseryWithOwner();

    $foreignChild = Child::factory()->create(['tenant_id' => $tenantB->id]);

    // The global scope hides the record entirely -> 404, never another tenant's data.
    $this->actingAs($ownerA)
        ->getJson("/api/v1/children/{$foreignChild->id}")
        ->assertNotFound();
});

it('resolves route-bound records inside the tenant context on web routes', function () {
    [$tenantA, $ownerA] = createNurseryWithOwner();
    [$tenantB] = createNurseryWithOwner();

    $foreignChild = Child::factory()->create(['tenant_id' => $tenantB->id]);

    // Route model binding must run after IdentifyTenant, so the TenantScope hides it.
    $this->actingAs($ownerA)
        ->get(route('nursery.children.show', $foreignChild->id))
        ->assertNotFound();

    $this->actingAs($ownerA)
        ->post(route('nursery.attendance.check-in', $foreignChild->id))
        ->assertNotFound();
});
