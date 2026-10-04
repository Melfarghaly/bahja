<?php

use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Models\FeeDiscount;
use App\Models\FeePlan;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\GuardianService;
use App\Services\Tuition\TuitionBillingService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));
    $this->tenant = Tenant::factory()->create();
    $this->father = User::factory()->create(['name' => 'الأب']);
    $this->mother = User::factory()->create(['name' => 'الأم']);
    $this->plan = FeePlan::factory()->create(['tenant_id' => $this->tenant->id, 'amount_piasters' => 185_050]);
    app(TenantContext::class)->set($this->tenant);
});

function splitChild($test, ?string $fatherShare, ?string $motherShare, ?FeeDiscount $discount = null): Child
{
    $child = Child::factory()->create(['tenant_id' => $test->tenant->id]);
    $guardians = app(GuardianService::class);
    $guardians->attach($child, $test->father, ['relationship' => 'father', 'role' => 'primary', 'is_payer' => true, 'billing_share_percent' => $fatherShare]);
    $guardians->attach($child, $test->mother, ['relationship' => 'mother', 'role' => 'viewer', 'is_payer' => true, 'billing_share_percent' => $motherShare]);
    ChildFeePlan::factory()->create(['tenant_id' => $test->tenant->id, 'child_id' => $child->id, 'fee_plan_id' => $test->plan->id, 'fee_discount_id' => $discount?->id, 'starts_on' => '2026-10-01']);

    return $child;
}

function runBilling($test)
{
    return app(TuitionBillingService::class)->generate($test->tenant, CarbonImmutable::parse('2026-10-01'));
}

it('splits a child\'s fees 60/40 into each parent\'s own invoice, summing exactly', function () {
    splitChild($this, '60', '40');

    $result = runBilling($this);

    $father = TuitionInvoice::where('payer_id', $this->father->id)->sole();
    $mother = TuitionInvoice::where('payer_id', $this->mother->id)->sole();
    expect($result->created)->toBe(2)
        ->and($result->shareWarnings)->toBe([])
        ->and($father->total_piasters)->toBe(111_030)
        ->and($mother->total_piasters)->toBe(74_020)
        ->and($father->total_piasters + $mother->total_piasters)->toBe(185_050)
        ->and($father->items()->first()->description)->toContain('(60%)');
});

it('splits fixed discounts too, so a discount is never granted twice', function () {
    $fixed = FeeDiscount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'staff', 'value_type' => 'fixed', 'value' => 50_000]);
    splitChild($this, '50', '50', $fixed);

    runBilling($this);

    expect((int) TuitionInvoice::sum('discount_piasters'))->toBe(50_000)
        ->and((int) TuitionInvoice::sum('total_piasters'))->toBe(185_050 - 50_000);
});

it('splits equally and warns when the shares do not add up to 100%', function () {
    $child = splitChild($this, '70', '20');

    $result = runBilling($this);

    expect($result->shareWarnings)->toHaveCount(1)
        ->and(TuitionInvoice::pluck('total_piasters')->sort()->values()->all())->toBe([92_525, 92_525]);
});

it('keeps a single payer at 100% whatever share is typed', function () {
    $child = Child::factory()->create(['tenant_id' => $this->tenant->id]);
    app(GuardianService::class)->attach($child, $this->father, ['relationship' => 'father', 'role' => 'primary', 'is_payer' => true, 'billing_share_percent' => '30']);
    ChildFeePlan::factory()->create(['tenant_id' => $this->tenant->id, 'child_id' => $child->id, 'fee_plan_id' => $this->plan->id, 'starts_on' => '2026-10-01']);

    runBilling($this);

    expect(TuitionInvoice::sole()->total_piasters)->toBe(185_050);
});

it('stores the share from the nursery guardian form and shows it', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($owner)->post(route('nursery.children.guardians.store', $child), [
        'name' => 'هبة', 'phone' => '01055556666', 'relationship' => 'mother', 'role' => 'viewer',
        'is_payer' => 1, 'billing_share_percent' => '33.33',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($child->guardians()->sole()->pivot->billing_share_bp)->toBe(3_333);
    $this->actingAs($owner)->get(route('nursery.children.show', $child))->assertSee('33.33%');

    $this->actingAs($owner)->post(route('nursery.children.guardians.store', $child), [
        'name' => 'x', 'phone' => '01055556667', 'relationship' => 'father', 'role' => 'viewer', 'is_payer' => 1, 'billing_share_percent' => '120',
    ])->assertSessionHasErrors('billing_share_percent');
});
