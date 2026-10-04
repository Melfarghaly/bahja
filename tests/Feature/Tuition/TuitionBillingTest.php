<?php

use App\Enums\FeeFrequency;
use App\Enums\InvoiceItemKind;
use App\Enums\LedgerAccount;
use App\Enums\TuitionInvoiceStatus;
use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Models\FeeDiscount;
use App\Models\FeePlan;
use App\Models\LedgerEntry;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\Tuition\LedgerService;
use App\Services\Tuition\TuitionBillingService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\SubscriptionPlanSeeder;

function billingChild(Tenant $tenant, ?User $payer = null, array $pivot = [], array $attributes = []): Child
{
    $child = Child::factory()->create(['tenant_id' => $tenant->id] + $attributes);

    if ($payer !== null) {
        $child->guardians()->attach($payer->id, $pivot + [
            'tenant_id' => $tenant->id, 'relationship' => 'mother', 'role' => 'primary', 'is_payer' => true,
        ]);
    }

    return $child;
}

function enroll(Child $child, FeePlan $plan, string $from = '2026-10-01', ?FeeDiscount $discount = null, ?string $until = null): ChildFeePlan
{
    return ChildFeePlan::factory()->create([
        'tenant_id' => $child->tenant_id, 'child_id' => $child->id, 'fee_plan_id' => $plan->id,
        'fee_discount_id' => $discount?->id, 'starts_on' => $from, 'ends_on' => $until,
    ]);
}

function bill(Tenant $tenant, string $period = '2026-10')
{
    app(TenantContext::class)->set($tenant);

    return app(TuitionBillingService::class)->generate($tenant, CarbonImmutable::createFromFormat('!Y-m', $period));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00'));
    $this->tenant = Tenant::factory()->create();
    $this->monthly = FeePlan::factory()->create(['tenant_id' => $this->tenant->id, 'amount_piasters' => 150_000]);
});

it('issues one family invoice per payer with the sibling discount from the second child', function () {
    $mother = User::factory()->create();
    FeeDiscount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'sibling', 'value_type' => 'percent', 'value' => 1_000]);
    enroll(billingChild($this->tenant, $mother), $this->monthly);
    enroll(billingChild($this->tenant, $mother), $this->monthly);

    $result = bill($this->tenant);

    $invoice = TuitionInvoice::sole();
    expect($result->created)->toBe(1)
        ->and($invoice->number)->toBe('INV-2026-000001')
        ->and($invoice->payer_id)->toBe($mother->id)
        ->and($invoice->subtotal_piasters)->toBe(300_000)
        ->and($invoice->discount_piasters)->toBe(15_000)
        ->and($invoice->total_piasters)->toBe(285_000)
        ->and($invoice->status)->toBe(TuitionInvoiceStatus::Open)
        ->and($invoice->due_on->toDateString())->toBe('2026-10-05')
        ->and($invoice->items()->where('kind', InvoiceItemKind::Discount)->count())->toBe(1);
});

it('posts a balanced ledger entry that agrees with the invoice', function () {
    $mother = User::factory()->create();
    FeeDiscount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'sibling', 'value' => 1_000]);
    enroll(billingChild($this->tenant, $mother), $this->monthly);
    enroll(billingChild($this->tenant, $mother), $this->monthly);

    bill($this->tenant);
    $invoice = TuitionInvoice::sole();
    $ledger = app(LedgerService::class);

    expect(LedgerEntry::sum('debit_piasters'))->toEqual(LedgerEntry::sum('credit_piasters'))
        ->and($ledger->receivableOn($invoice))->toBe($invoice->total_piasters)
        ->and($ledger->balances($this->tenant))->toMatchArray([
            LedgerAccount::Receivable->value => 285_000,
            LedgerAccount::Discounts->value => 15_000,
            LedgerAccount::TuitionRevenue->value => -300_000,
        ]);
});

it('is idempotent: running the same period twice never double-bills', function () {
    enroll(billingChild($this->tenant, User::factory()->create()), $this->monthly);

    bill($this->tenant);
    $second = bill($this->tenant);

    expect($second->created)->toBe(0)
        ->and($second->alreadyInvoiced)->toBe(1)
        ->and(TuitionInvoice::count())->toBe(1)
        ->and(LedgerEntry::count())->toBe(2);
});

it('applies a child discount first and the sibling discount on the remainder', function () {
    $father = User::factory()->create();
    FeeDiscount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'sibling', 'value' => 1_000]);
    $scholarship = FeeDiscount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'scholarship', 'value' => 5_000]);
    enroll(billingChild($this->tenant, $father), $this->monthly);
    enroll(billingChild($this->tenant, $father), $this->monthly, discount: $scholarship);

    bill($this->tenant);

    // Child 2: 1500 − 50% = 750, then 10% sibling on 750 = 75 → discounts 825.
    expect(TuitionInvoice::sole()->discount_piasters)->toBe(82_500)
        ->and(TuitionInvoice::sole()->total_piasters)->toBe(217_500);
});

it('caps a fixed discount at the fee and settles a fully discounted invoice', function () {
    $free = FeeDiscount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'scholarship', 'value_type' => 'fixed', 'value' => 999_999]);
    enroll(billingChild($this->tenant, User::factory()->create()), $this->monthly, discount: $free);

    bill($this->tenant);
    $invoice = TuitionInvoice::sole();

    expect($invoice->total_piasters)->toBe(0)
        ->and($invoice->status)->toBe(TuitionInvoiceStatus::Paid)
        ->and(app(LedgerService::class)->balances($this->tenant))->not->toHaveKey(LedgerAccount::Receivable->value);
});

it('bills each frequency only in its months', function (FeeFrequency $frequency, array $billedMonths) {
    $plan = FeePlan::factory()->create(['tenant_id' => $this->tenant->id, 'frequency' => $frequency]);
    enroll(billingChild($this->tenant, User::factory()->create()), $plan, '2026-10-01');

    $billed = [];
    foreach (['2026-09', '2026-10', '2026-11', '2026-12', '2027-01', '2027-10'] as $month) {
        if (bill($this->tenant, $month)->created === 1) {
            $billed[] = $month;
        }
    }

    expect($billed)->toBe($billedMonths);
})->with([
    'monthly' => [FeeFrequency::Monthly, ['2026-10', '2026-11', '2026-12', '2027-01', '2027-10']],
    'term' => [FeeFrequency::Term, ['2026-10', '2027-01', '2027-10']],
    'annual' => [FeeFrequency::Annual, ['2026-10', '2027-10']],
    'one-off' => [FeeFrequency::OneOff, ['2026-10']],
]);

it('skips ended enrollments, paused plans and children who left', function () {
    $payer = User::factory()->create();
    enroll(billingChild($this->tenant, $payer), $this->monthly, '2026-08-01', until: '2026-09-30');
    enroll(billingChild($this->tenant, $payer), FeePlan::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]));
    enroll(billingChild($this->tenant, $payer, attributes: ['status' => 'withdrawn']), $this->monthly);

    expect(bill($this->tenant)->created)->toBe(0);
});

it('invoices the flagged payer, never a custody-blocked guardian, and reports children without a payer', function () {
    $primary = User::factory()->create();
    $payer = User::factory()->create();
    $child = billingChild($this->tenant, $primary, ['is_payer' => false]);
    $child->guardians()->attach($payer->id, ['tenant_id' => $this->tenant->id, 'relationship' => 'father', 'role' => 'viewer', 'is_payer' => true]);
    enroll($child, $this->monthly);

    $blocked = User::factory()->create();
    $orphan = billingChild($this->tenant, $blocked, ['custody_flag' => 'blocked'], ['first_name' => 'سلمى', 'last_name' => 'أحمد']);
    enroll($orphan, $this->monthly);

    $result = bill($this->tenant);

    expect(TuitionInvoice::sole()->payer_id)->toBe($payer->id)
        ->and($result->unbillableChildren)->toBe(['سلمى أحمد']);
});

it('uses the nursery\'s due day and never issues an already-overdue invoice', function () {
    $this->tenant->update(['settings' => ['tuition_due_day' => 10]]);
    enroll(billingChild($this->tenant, User::factory()->create()), $this->monthly, '2026-09-01');

    bill($this->tenant, '2026-09');   // billing September late, on Oct 1st

    expect(TuitionInvoice::sole()->due_on->toDateString())->toBe('2026-10-01');
});

it('runs monthly only for nurseries with Bahga Pay released and Auto Collection in their plan', function () {
    (new SubscriptionPlanSeeder)->run();
    $pro = SubscriptionPlan::where('slug', 'pro')->value('id');
    $basic = SubscriptionPlan::where('slug', 'basic')->value('id');

    $eligible = $this->tenant;
    Subscription::factory()->create(['tenant_id' => $eligible->id, 'subscription_plan_id' => $pro, 'status' => 'active']);
    enableBahgaPay($eligible);
    enroll(billingChild($eligible, User::factory()->create()), $this->monthly);

    $basicTenant = Tenant::factory()->create();
    Subscription::factory()->create(['tenant_id' => $basicTenant->id, 'subscription_plan_id' => $basic, 'status' => 'active']);
    enableBahgaPay($basicTenant);
    enroll(billingChild($basicTenant, User::factory()->create()), FeePlan::factory()->create(['tenant_id' => $basicTenant->id]));

    $this->artisan('tuition:generate', ['--period' => '2026-10'])->assertSuccessful();

    expect(TuitionInvoice::withoutGlobalScopes()->pluck('tenant_id')->all())->toBe([$eligible->id]);
});

it('rejects a malformed period on the command', function () {
    $this->artisan('tuition:generate', ['--period' => 'October'])->assertExitCode(2);
});
