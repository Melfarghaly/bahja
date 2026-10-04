<?php

use App\Models\Tenant;
use App\Models\User;

it('lets a super admin suspend and reactivate a nursery', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->patch(route('admin.nurseries.suspend', $tenant))
        ->assertRedirect();

    expect($tenant->fresh()->status->value)->toBe('suspended');

    $this->actingAs($admin)
        ->patch(route('admin.nurseries.activate', $tenant))
        ->assertRedirect();

    expect($tenant->fresh()->status->value)->toBe('active');
});

it('shows only matching nurseries when searching', function () {
    $admin = User::factory()->superAdmin()->create();
    Tenant::factory()->create(['name' => 'Sunrise Nursery']);
    Tenant::factory()->create(['name' => 'Moonlight Nursery']);

    $this->actingAs($admin)
        ->get(route('admin.nurseries.index', ['q' => 'Sunrise']))
        ->assertOk()
        ->assertSee('Sunrise Nursery')
        ->assertDontSee('Moonlight Nursery');
});
