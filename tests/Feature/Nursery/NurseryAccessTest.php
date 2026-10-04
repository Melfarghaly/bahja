<?php

use App\Models\Child;
use App\Models\User;

it('redirects guests to login', function () {
    $this->get('/app')->assertRedirect('/login');
});

it('forbids a user with no tenant membership', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/app')->assertForbidden();
});

it('lets a nursery owner open the dashboard', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $this->actingAs($owner)->get('/app')->assertOk()->assertViewIs('nursery.dashboard');
});

it('lets a teacher in but blocks admin-only screens', function () {
    [$tenant] = createNurseryWithOwner();
    $teacher = attachTeacher($tenant);

    $this->actingAs($teacher)->get('/app')->assertOk();
    $this->actingAs($teacher)->get('/app/teachers')->assertForbidden();
    $this->actingAs($teacher)->get('/app/settings')->assertForbidden();
});

it('scopes the children list to the current nursery only', function () {
    [$tenantA, $owner] = createNurseryWithOwner();
    [$tenantB] = createNurseryWithOwner();

    Child::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'first_name' => 'Aمine']);
    Child::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($owner)->get('/app/children');
    $response->assertOk();
    expect($response->viewData('children')->total())->toBe(2);
});
