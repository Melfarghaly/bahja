<?php

use App\Models\PlatformSetting;
use App\Models\User;

it('persists branding changes', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.settings.update'), [
            'brand_name' => 'My Nurseries',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'accent_color' => '#778899',
            'support_email' => 'help@example.com',
        ])
        ->assertRedirect();

    $settings = PlatformSetting::current();
    expect($settings->brand_name)->toBe('My Nurseries');
    expect($settings->primary_color)->toBe('#112233');
});

it('rejects an invalid color value', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('admin.settings.update'), [
            'brand_name' => 'X',
            'primary_color' => 'not-a-color',
            'secondary_color' => '#445566',
            'accent_color' => '#778899',
        ])
        ->assertSessionHasErrors('primary_color');
});
