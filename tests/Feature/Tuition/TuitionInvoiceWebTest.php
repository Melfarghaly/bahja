<?php

use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Models\FeePlan;
use App\Models\TuitionInvoice;
use App\Models\User;

beforeEach(function () {
    [$this->tenant, $this->owner] = createNurseryWithOwner();
    enableBahgaPay($this->tenant);
    $this->plan = FeePlan::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'المصروفات الشهرية', 'amount_piasters' => 120_000]);
});

function enrolledChild($test, ?User $payer, string $name = 'يوسف'): Child
{
    $child = Child::factory()->create(['tenant_id' => $test->tenant->id, 'first_name' => $name]);
    if ($payer) {
        $child->guardians()->attach($payer->id, ['tenant_id' => $test->tenant->id, 'relationship' => 'mother', 'role' => 'primary', 'is_payer' => true]);
    }
    ChildFeePlan::factory()->create(['tenant_id' => $test->tenant->id, 'child_id' => $child->id, 'fee_plan_id' => $test->plan->id, 'starts_on' => now()->startOfMonth()]);

    return $child;
}

it('generates the month\'s invoices from the UI and lists them', function () {
    $mother = User::factory()->create(['name' => 'منى']);
    enrolledChild($this, $mother);

    $this->actingAs($this->owner)
        ->post(route('nursery.finance.invoices.generate'), ['period' => now()->format('Y-m')])
        ->assertRedirect(route('nursery.finance.invoices.index', ['period' => now()->format('Y-m')]))
        ->assertSessionHas('status', fn ($m) => str_contains($m, 'تم إصدار 1 فاتورة'));

    $this->actingAs($this->owner)
        ->get(route('nursery.finance.invoices.index'))
        ->assertOk()
        ->assertSee('منى')
        ->assertSee('1,200 ج.م');
});

it('warns about children with fees but no paying guardian', function () {
    enrolledChild($this, null, 'كريم');

    $this->actingAs($this->owner)
        ->post(route('nursery.finance.invoices.generate'), ['period' => now()->format('Y-m')])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'كريم'));
});

it('shows an invoice with its lines and balance', function () {
    enrolledChild($this, User::factory()->create());
    $this->actingAs($this->owner)->post(route('nursery.finance.invoices.generate'), ['period' => now()->format('Y-m')]);
    $invoice = TuitionInvoice::sole();

    $this->actingAs($this->owner)
        ->get(route('nursery.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertSee('المصروفات الشهرية — يوسف');
});

it('filters overdue invoices', function () {
    $payer = User::factory()->create();
    TuitionInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'payer_id' => $payer->id, 'number' => 'INV-LATE', 'due_on' => now()->subDays(10), 'period_start' => now()->subMonth()->startOfMonth()]);
    TuitionInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'payer_id' => $payer->id, 'number' => 'INV-FINE', 'due_on' => now()->addDays(5)]);

    $this->actingAs($this->owner)
        ->get(route('nursery.finance.invoices.index', ['overdue' => 1]))
        ->assertOk()
        ->assertSee('INV-LATE')
        ->assertDontSee('INV-FINE');
});

it('never shows another nursery\'s invoice', function () {
    $foreign = TuitionInvoice::factory()->create();

    $this->actingAs($this->owner)->get(route('nursery.finance.invoices.show', $foreign))->assertNotFound();
});

it('validates the period', function () {
    $this->actingAs($this->owner)
        ->post(route('nursery.finance.invoices.generate'), ['period' => '2026-13'])
        ->assertSessionHasErrors('period');
});
