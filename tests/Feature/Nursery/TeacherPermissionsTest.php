<?php

use App\Models\Child;
use App\Models\User;

beforeEach(function () {
    [$this->tenant, $this->owner] = createNurseryWithOwner();
    $this->teacher = attachTeacher($this->tenant);
    $this->child = Child::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('lets teachers read children but not change them, their guardians or pickup rights (web)', function () {
    $this->actingAs($this->teacher)->get(route('nursery.children.show', $this->child))
        ->assertOk()->assertDontSee('إضافة وليّ أمر')->assertDontSee('تعديل البيانات');

    $this->actingAs($this->teacher)->get(route('nursery.children.create'))->assertForbidden();
    $this->actingAs($this->teacher)->put(route('nursery.children.update', $this->child), ['first_name' => 'x'])->assertForbidden();
    $this->actingAs($this->teacher)->post(route('nursery.children.guardians.store', $this->child), [
        'name' => 'Stranger', 'phone' => '01099990000', 'relationship' => 'other', 'role' => 'pickup_authorized', 'can_pickup' => 1,
    ])->assertForbidden();

    expect($this->child->guardians()->count())->toBe(0);
});

it('lets teachers read children but not change them, their guardians or pickup rights (API)', function () {
    $stranger = User::factory()->create();

    $this->actingAs($this->teacher)->getJson("/api/v1/children/{$this->child->id}")->assertOk();
    $this->actingAs($this->teacher)->patchJson("/api/v1/children/{$this->child->id}", ['first_name' => 'x'])->assertForbidden();
    $this->actingAs($this->teacher)->postJson("/api/v1/children/{$this->child->id}/guardians", [
        'user_id' => $stranger->id, 'relationship' => 'other', 'role' => 'pickup_authorized', 'can_pickup' => true,
    ])->assertForbidden();
});

it('still lets owners manage children and guardians', function () {
    $this->actingAs($this->owner)->get(route('nursery.children.show', $this->child))->assertOk()->assertSee('إضافة وليّ أمر');
    $this->actingAs($this->owner)->get(route('nursery.children.create'))->assertOk();
});
