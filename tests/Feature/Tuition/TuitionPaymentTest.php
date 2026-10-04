<?php

use App\Enums\AuditAction;
use App\Enums\LedgerAccount;
use App\Enums\TuitionInvoiceStatus;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Models\FeePlan;
use App\Models\LedgerEntry;
use App\Models\TuitionInvoice;
use App\Models\TuitionPayment;
use App\Models\User;
use App\Services\Tuition\LedgerService;

beforeEach(function () {
    [$this->tenant, $this->owner] = createNurseryWithOwner();
    enableBahgaPay($this->tenant);

    $plan = FeePlan::factory()->create(['tenant_id' => $this->tenant->id, 'amount_piasters' => 150_000]);
    $this->payer = User::factory()->create();
    $child = Child::factory()->create(['tenant_id' => $this->tenant->id]);
    $child->guardians()->attach($this->payer->id, ['tenant_id' => $this->tenant->id, 'relationship' => 'mother', 'role' => 'primary', 'is_payer' => true]);
    ChildFeePlan::factory()->create(['tenant_id' => $this->tenant->id, 'child_id' => $child->id, 'fee_plan_id' => $plan->id, 'starts_on' => now()->startOfMonth()]);

    $this->actingAs($this->owner)->post(route('nursery.finance.invoices.generate'), ['period' => now()->format('Y-m')]);
    $this->invoice = TuitionInvoice::sole();
});

function pay($test, array $data)
{
    return $test->actingAs($test->owner)->post(route('nursery.finance.payments.store', $test->invoice), $data);
}

it('records partial then full payment with receipts, status, ledger and audit', function () {
    pay($this, ['amount' => '500', 'method' => 'cash'])->assertRedirect()->assertSessionHasNoErrors();

    $invoice = $this->invoice->fresh();
    expect($invoice->status)->toBe(TuitionInvoiceStatus::PartiallyPaid)
        ->and($invoice->paid_piasters)->toBe(50_000)
        ->and(TuitionPayment::sole()->receipt_number)->toBe('RCT-'.now()->year.'-000001');

    pay($this, ['amount' => '1000', 'method' => 'instapay', 'reference' => 'IP-778899'])->assertSessionHasNoErrors();

    $invoice = $invoice->fresh();
    $ledger = app(LedgerService::class);
    expect($invoice->status)->toBe(TuitionInvoiceStatus::Paid)
        ->and($invoice->balance()->piasters)->toBe(0)
        ->and($ledger->receivableOn($invoice))->toBe(0)
        ->and($ledger->balances($this->tenant))->toMatchArray([
            LedgerAccount::Cash->value => 50_000,
            LedgerAccount::Bank->value => 100_000,
            LedgerAccount::Receivable->value => 0,
        ])
        ->and(AuditLog::withoutGlobalScopes()->where('action', AuditAction::TuitionPaymentRecorded)->count())->toBe(2);
});

it('refuses overpayment, payments on settled invoices, and untraceable transfers', function () {
    pay($this, ['amount' => '1500.01', 'method' => 'cash'])->assertSessionHasErrors('amount');
    pay($this, ['amount' => '100', 'method' => 'bank_transfer'])->assertSessionHasErrors('reference');
    pay($this, ['amount' => '100', 'method' => 'card', 'reference' => 'x'])->assertSessionHasErrors('method'); // online only

    pay($this, ['amount' => '1500', 'method' => 'cash'])->assertSessionHasNoErrors();
    pay($this, ['amount' => '1', 'method' => 'cash'])->assertSessionHasErrors('amount');

    expect(TuitionPayment::count())->toBe(1);
});

it('voids a payment with a reversing ledger entry, reopening the invoice', function () {
    pay($this, ['amount' => '1500', 'method' => 'cash']);
    $payment = TuitionPayment::sole();
    $entriesBefore = LedgerEntry::count();

    $this->actingAs($this->owner)
        ->post(route('nursery.finance.payments.void', $payment), ['reason' => 'إيصال مكرر'])
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->isVoid())->toBeTrue()
        ->and($this->invoice->fresh()->status)->toBe(TuitionInvoiceStatus::Open)
        ->and($this->invoice->fresh()->paid_piasters)->toBe(0)
        ->and(LedgerEntry::count())->toBe($entriesBefore + 2)                       // history kept
        ->and(app(LedgerService::class)->balances($this->tenant)[LedgerAccount::Cash->value])->toBe(0);

    $this->actingAs($this->owner)
        ->post(route('nursery.finance.payments.void', $payment), ['reason' => 'again'])
        ->assertSessionHasErrors('reason');
});

it('voids an unpaid invoice and lets the family be re-billed for the month', function () {
    pay($this, ['amount' => '100', 'method' => 'cash']);
    $this->actingAs($this->owner)
        ->post(route('nursery.finance.invoices.void', $this->invoice), ['reason' => 'خطأ'])
        ->assertSessionHasErrors('reason');                                         // has a live payment

    $this->actingAs($this->owner)->post(route('nursery.finance.payments.void', TuitionPayment::sole()), ['reason' => 'خطأ']);
    $this->actingAs($this->owner)
        ->post(route('nursery.finance.invoices.void', $this->invoice), ['reason' => 'صدرت بمبلغ خاطئ'])
        ->assertSessionHasNoErrors();

    expect($this->invoice->fresh()->status)->toBe(TuitionInvoiceStatus::Void)
        ->and(app(LedgerService::class)->receivableOn($this->invoice))->toBe(0);

    $this->actingAs($this->owner)->post(route('nursery.finance.invoices.generate'), ['period' => now()->format('Y-m')]);

    expect(TuitionInvoice::count())->toBe(2)
        ->and(TuitionInvoice::where('status', 'open')->sole()->number)->not->toBe($this->invoice->number);
});

it('keeps every ledger posting balanced across a full lifecycle', function () {
    pay($this, ['amount' => '700', 'method' => 'cash']);
    pay($this, ['amount' => '800', 'method' => 'wallet', 'reference' => 'W-1']);
    $this->actingAs($this->owner)->post(route('nursery.finance.payments.void', TuitionPayment::first()), ['reason' => 'خطأ']);

    LedgerEntry::all()->groupBy('transaction_id')->each(function ($lines) {
        expect($lines->sum('debit_piasters'))->toBe($lines->sum('credit_piasters'));
    });

    expect(app(LedgerService::class)->receivableOn($this->invoice))->toBe($this->invoice->fresh()->balance()->piasters);
});

it('renders a printable receipt, marked when void', function () {
    pay($this, ['amount' => '1500', 'method' => 'cash']);
    $payment = TuitionPayment::sole();

    $this->actingAs($this->owner)->get(route('nursery.finance.payments.receipt', $payment))
        ->assertOk()->assertSee($payment->receipt_number)->assertSee('1,500 ج.م')->assertDontSee('>ملغي<', false);

    $this->actingAs($this->owner)->post(route('nursery.finance.payments.void', $payment), ['reason' => 'خطأ']);

    $this->actingAs($this->owner)->get(route('nursery.finance.payments.receipt', $payment))->assertOk()->assertSee('ملغي');
});

it('never exposes another nursery\'s receipts or accepts payments on its invoices', function () {
    $foreignPayment = TuitionPayment::factory()->create();

    $this->actingAs($this->owner)->get(route('nursery.finance.payments.receipt', $foreignPayment))->assertNotFound();
    $this->actingAs($this->owner)->post(route('nursery.finance.payments.store', $foreignPayment->tuition_invoice_id), ['amount' => '1', 'method' => 'cash'])->assertNotFound();
});

it('keeps teachers out of payments', function () {
    $teacher = attachTeacher($this->tenant);

    $this->actingAs($teacher)->post(route('nursery.finance.payments.store', $this->invoice), ['amount' => '1', 'method' => 'cash'])->assertForbidden();
});
