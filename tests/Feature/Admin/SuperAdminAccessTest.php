<?php

use App\Models\User;

it('redirects guests away from the admin area', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('forbids a regular user from the admin area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('allows a super admin into the dashboard', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/admin')->assertOk()->assertViewIs('admin.dashboard');
});
