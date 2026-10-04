<?php

use App\Models\Child;
use App\Models\User;

it('issues a bearer token that authenticates tenant API calls', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $owner->update(['password' => 'secret-pass']);
    Child::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    $token = $this->postJson('/api/v1/auth/tokens', [
        'login' => $owner->email,
        'password' => 'secret-pass',
        'device_name' => 'teacher-phone',
    ])->assertCreated()->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/children')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('accepts a phone number as the login', function () {
    $user = User::factory()->create(['phone' => '01000000001', 'password' => 'secret-pass']);

    $this->postJson('/api/v1/auth/tokens', [
        'login' => $user->phone,
        'password' => 'secret-pass',
        'device_name' => 'parent-phone',
    ])->assertCreated();
});

it('rejects wrong credentials', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);

    $this->postJson('/api/v1/auth/tokens', [
        'login' => $user->email,
        'password' => 'wrong',
        'device_name' => 'x',
    ])->assertUnprocessable()->assertJsonValidationErrors('login');
});

it('keeps a single token per device', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);
    $payload = ['login' => $user->email, 'password' => 'secret-pass', 'device_name' => 'tablet'];

    $this->postJson('/api/v1/auth/tokens', $payload)->assertCreated();
    $this->postJson('/api/v1/auth/tokens', $payload)->assertCreated();

    expect($user->tokens()->count())->toBe(1);
});

it('revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phone')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/auth/tokens/current')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('rejects unauthenticated API calls', function () {
    $this->getJson('/api/v1/children')->assertUnauthorized();
});
