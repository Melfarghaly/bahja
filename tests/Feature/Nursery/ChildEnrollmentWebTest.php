<?php

use App\Models\Child;
use App\Models\User;

it('enrolls a child and auto-creates the primary guardian by phone', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $response = $this->actingAs($owner)->post('/app/children', [
        'first_name' => 'Yousef',
        'last_name' => 'Hassan',
        'birth_date' => '2022-03-01',
        'gender' => 'male',
        'guardians' => [
            ['name' => 'Mona', 'phone' => '01099887766', 'relationship' => 'mother', 'role' => 'primary', 'can_pickup' => '1', 'can_view_wall' => '1', 'is_payer' => '1'],
        ],
    ]);

    $child = Child::firstWhere('first_name', 'Yousef');
    $response->assertRedirect(route('nursery.children.show', $child));

    $guardian = User::firstWhere('phone', '01099887766');
    expect($guardian)->not->toBeNull();
    expect($child->guardians()->where('users.id', $guardian->id)->wherePivot('can_pickup', true)->exists())->toBeTrue();
});
