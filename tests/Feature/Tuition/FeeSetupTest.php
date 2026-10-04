<?php

use App\Enums\AuditAction;
use App\Enums\DiscountValueType;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Models\Classroom;
use App\Models\FeeDiscount;
use App\Models\FeePlan;

beforeEach(function () {
    [$this->tenant, $this->owner] = createNurseryWithOwner();
    enableBahgaPay($this->tenant);
});

it('creates a fee plan from a pound amount, stored as exact piasters', function () {
    $this->actingAs($this->owner)
        ->post(route('nursery.finance.fee-plans.store'), ['name' => 'المصروفات الشهرية', 'amount' => '1,850.50', 'frequency' => 'monthly'])
        ->assertSessionHasNoErrors();

    $plan = FeePlan::sole();
    expect($plan->amount_piasters)->toBe(185_050)
        ->and($plan->tenant_id)->toBe($this->tenant->id)
        ->and(AuditLog::withoutGlobalScopes()->where('action', AuditAction::FeePlanSaved)->count())->toBe(1);
});

it('rejects invalid amounts and another nursery\'s classroom', function () {
    $foreignClassroom = Classroom::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('nursery.finance.fee-plans.store'), ['name' => 'x', 'amount' => '12.345', 'frequency' => 'monthly', 'classroom_id' => $foreignClassroom->id])
        ->assertSessionHasErrors(['amount', 'classroom_id']);

    $this->actingAs($this->owner)
        ->post(route('nursery.finance.fee-plans.store'), ['name' => 'x', 'amount' => '0', 'frequency' => 'monthly'])
        ->assertSessionHasErrors('amount');
});

it('stores percentage discounts as basis points and keeps one active sibling rule', function () {
    $this->actingAs($this->owner)->post(route('nursery.finance.discounts.store'), [
        'name' => 'إخوة 10%', 'type' => 'sibling', 'value_type' => 'percent', 'value' => '10',
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('nursery.finance.discounts.store'), [
        'name' => 'إخوة 12.5%', 'type' => 'sibling', 'value_type' => 'percent', 'value' => '12.5',
    ])->assertSessionHasNoErrors();

    $active = FeeDiscount::where('is_active', true)->sole();
    expect($active->value)->toBe(1_250)
        ->and($active->value_type)->toBe(DiscountValueType::Percent)
        ->and(FeeDiscount::count())->toBe(2);

    $this->actingAs($this->owner)->post(route('nursery.finance.discounts.store'), [
        'name' => 'bad', 'type' => 'staff', 'value_type' => 'percent', 'value' => '150',
    ])->assertSessionHasErrors('value');
});

it('enrolls a child in a fee plan from the start of the chosen month', function () {
    $child = Child::factory()->create(['tenant_id' => $this->tenant->id]);
    $plan = FeePlan::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff = FeeDiscount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'staff']);

    $this->actingAs($this->owner)
        ->post(route('nursery.finance.child-fees.store', $child), ['fee_plan_id' => $plan->id, 'fee_discount_id' => $staff->id, 'starts_on' => '2026-11'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $assignment = ChildFeePlan::sole();
    expect($assignment->starts_on->toDateString())->toBe('2026-11-01')
        ->and($assignment->fee_discount_id)->toBe($staff->id);

    // The same open plan cannot be assigned twice.
    $this->actingAs($this->owner)
        ->post(route('nursery.finance.child-fees.store', $child), ['fee_plan_id' => $plan->id, 'starts_on' => '2026-12'])
        ->assertRedirect()
        ->assertSessionHasErrors('fee_plan_id');
});

it('refuses pinning the automatic sibling discount or a foreign plan to a child', function () {
    $child = Child::factory()->create(['tenant_id' => $this->tenant->id]);
    $sibling = FeeDiscount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'sibling']);
    $foreignPlan = FeePlan::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('nursery.finance.child-fees.store', $child), ['fee_plan_id' => $foreignPlan->id, 'fee_discount_id' => $sibling->id, 'starts_on' => '2026-11'])
        ->assertSessionHasErrors(['fee_plan_id', 'fee_discount_id']);
});

it('ends an enrollment at the end of the chosen month', function () {
    $assignment = ChildFeePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'child_id' => Child::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'fee_plan_id' => FeePlan::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'starts_on' => '2026-09-01',
    ]);

    $this->actingAs($this->owner)
        ->patch(route('nursery.finance.child-fees.end', $assignment), ['ends_on' => '2026-12'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($assignment->fresh()->ends_on->toDateString())->toBe('2026-12-31');
});

it('renders the setup page and the child fees panel', function () {
    $child = Child::factory()->create(['tenant_id' => $this->tenant->id]);
    FeePlan::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'رسوم الأتوبيس', 'amount_piasters' => 60_000]);

    $this->actingAs($this->owner)->get(route('nursery.finance.setup'))->assertOk()->assertSee('رسوم الأتوبيس')->assertSee('600 ج.م');
    $this->actingAs($this->owner)->get(route('nursery.children.show', $child))->assertOk()->assertSee('الرسوم المسجّلة');
});

it('hides Bahga Pay from nurseries it has not been released to, and from teachers', function () {
    [$other, $otherOwner] = createNurseryWithOwner();
    $teacher = attachTeacher($this->tenant);

    $this->actingAs($otherOwner)->get(route('nursery.finance.setup'))->assertNotFound();
    $this->actingAs($teacher)->get(route('nursery.finance.setup'))->assertForbidden();
});

it('never lets one nursery edit another nursery\'s fee plan', function () {
    $foreign = FeePlan::factory()->create();

    $this->actingAs($this->owner)
        ->put(route('nursery.finance.fee-plans.update', $foreign), ['name' => 'hack', 'amount' => '1', 'frequency' => 'monthly'])
        ->assertNotFound();
});

it('lets the nursery choose its invoice due day without wiping other settings', function () {
    $this->tenant->update(['settings' => ['theme' => 'teal']]);

    $this->actingAs($this->owner)
        ->put(route('nursery.settings.update'), ['name' => $this->tenant->name, 'tuition_due_day' => 10])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->settings)->toBe(['theme' => 'teal', 'tuition_due_day' => 10]);

    $this->actingAs($this->owner)
        ->put(route('nursery.settings.update'), ['name' => $this->tenant->name, 'tuition_due_day' => 31])
        ->assertSessionHasErrors('tuition_due_day');

    $this->actingAs($this->owner)->get(route('nursery.settings.edit'))->assertOk()->assertSee('يوم استحقاق فواتير المصروفات');
});
