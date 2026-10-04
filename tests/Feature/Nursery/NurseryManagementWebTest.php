<?php

use App\Models\Classroom;
use App\Models\User;

it('lets an owner add a teacher (creating the user and both pivots)', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $this->actingAs($owner)->post(route('nursery.teachers.store'), [
        'name' => 'Sara', 'phone' => '01055443322', 'role' => 'teacher',
        'employment_type' => 'full_time',
    ])->assertRedirect();

    $teacher = User::firstWhere('phone', '01055443322');
    expect($teacher)->not->toBeNull();
    expect($teacher->teachesIn($tenant->fresh()))->toBeTrue();
});

it('lets an owner create a classroom scoped to the nursery', function () {
    [$tenant, $owner] = createNurseryWithOwner();

    $this->actingAs($owner)->post(route('nursery.classrooms.store'), [
        'name' => 'Sunflowers', 'capacity' => 20,
    ])->assertRedirect();

    expect(Classroom::where('tenant_id', $tenant->id)->where('name', 'Sunflowers')->exists())->toBeTrue();
});
