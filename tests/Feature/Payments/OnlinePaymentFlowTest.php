<?php

use App\Enums\LedgerAccount;
use App\Enums\PaymentIntentStatus;
use App\Enums\TuitionInvoiceStatus;
use App\Enums\TuitionPaymentMethod;
use App\Models\PaymentIntent;
use App\Models\TenantEntitlementOverride;
use App\Models\TuitionPayment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\EntitlementService;
use App\Services\Tuition\LedgerService;
use App\Services\Tuition\TuitionPaymentService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureGateways();
    Http::fake([
        'accept.paymob.test/v1/intention/' => Http::response(['id' => 'pi_1', 'client_secret' => 'cs_1'], 201),
        'fawry.test/*' => Http::response(['statusCode' => 200, 'referenceNumber' => '966512345']),
    ]);
    [$this->tenant, $this->payer, $this->invoice] = onlinePayingFamily(150_000);
});

function checkout($test, string $gateway = 'paymob')
{
    return $test->actingAs($test->payer)->postJson("/api/v1/me/invoices/{$test->invoice->id}/checkout", ['gateway' => $gateway]);
}

function postPaymob($test, array $body, string $hmac)
{
    return $test->postJson('/api/webhooks/payments/paymob?hmac='.$hmac, $body);
}

it('lets the payer open a card checkout for the outstanding balance', function () {
    checkout($this)
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://accept.paymob.test/unifiedcheckout/?publicKey=pk_test&clientSecret=cs_1')
        ->assertJsonPath('data.amount.piasters', 150_000);

    $intent = PaymentIntent::sole();
    expect($intent->status)->toBe(PaymentIntentStatus::Pending)
        ->and(PaymentIntent::tenantIdFromReference($intent->merchant_reference))->toBe($this->tenant->id);
});

it('reuses a live checkout instead of opening a new one on every tap', function () {
    checkout($this, 'fawry')->assertCreated()->assertJsonPath('data.payment_code', '966512345');
    checkout($this, 'fawry')->assertOk();   // 200: the existing intent, not a new one

    expect(PaymentIntent::count())->toBe(1);
    Http::assertSentCount(1);
});

it('settles the invoice once from a signed webhook — duplicates change nothing', function () {
    checkout($this);
    $intent = PaymentIntent::sole();
    [$body, $hmac] = paymobCallback($intent->merchant_reference, 150_000);

    postPaymob($this, $body, $hmac)->assertOk()->assertJsonPath('status', 'accepted');
    postPaymob($this, $body, $hmac)->assertOk()->assertJsonPath('status', 'duplicate');

    $payment = TuitionPayment::sole();
    expect($intent->fresh()->status)->toBe(PaymentIntentStatus::Succeeded)
        ->and($payment->method)->toBe(TuitionPaymentMethod::Card)
        ->and($payment->payment_intent_id)->toBe($intent->id)
        ->and($payment->received_by)->toBeNull()
        ->and($this->invoice->fresh()->status)->toBe(TuitionInvoiceStatus::Paid)
        ->and(app(LedgerService::class)->receivableOn($this->invoice))->toBe(0)
        ->and(app(LedgerService::class)->balances($this->tenant)[LedgerAccount::Bank->value])->toBe(150_000)
        ->and(WebhookEvent::sole()->outcome)->toBe('applied');
});

it('ignores a second, different success event for an already settled intent', function () {
    checkout($this);
    $reference = PaymentIntent::sole()->merchant_reference;

    [$first, $h1] = paymobCallback($reference, 150_000, id: 1);
    [$second, $h2] = paymobCallback($reference, 150_000, id: 2);
    postPaymob($this, $first, $h1);
    postPaymob($this, $second, $h2)->assertOk();

    expect(TuitionPayment::count())->toBe(1)
        ->and(WebhookEvent::orderBy('id')->pluck('outcome')->all())->toBe(['applied', 'duplicate']);
});

it('rejects forged webhooks and keeps them for forensics', function () {
    checkout($this);
    [$body] = paymobCallback(PaymentIntent::sole()->merchant_reference, 150_000);

    postPaymob($this, $body, str_repeat('0', 128))->assertUnauthorized();

    expect(TuitionPayment::count())->toBe(0)
        ->and(WebhookEvent::sole())->signature_valid->toBeFalse()->outcome->toBe('rejected');
});

it('marks a declined payment as failed without touching the invoice', function () {
    checkout($this);
    [$body, $hmac] = paymobCallback(PaymentIntent::sole()->merchant_reference, 150_000, success: false);

    postPaymob($this, $body, $hmac)->assertOk();

    expect(PaymentIntent::sole()->status)->toBe(PaymentIntentStatus::Failed)
        ->and($this->invoice->fresh()->status)->toBe(TuitionInvoiceStatus::Open);
});

it('parks money it cannot apply for human review instead of guessing', function () {
    checkout($this);
    $intent = PaymentIntent::sole();

    // Meanwhile the parent paid 500 in cash at the nursery.
    app(TuitionPaymentService::class)->record($this->invoice, ['amount' => '500', 'method' => 'cash'], $this->payer);

    [$body, $hmac] = paymobCallback($intent->merchant_reference, 150_000);
    postPaymob($this, $body, $hmac)->assertOk();

    expect($intent->fresh()->status)->toBe(PaymentIntentStatus::NeedsReview)
        ->and($intent->fresh()->failure_reason)->toContain('أكبر من المتبقي')
        ->and(TuitionPayment::count())->toBe(1)                      // only the cash one
        ->and(WebhookEvent::sole()->outcome)->toBe('needs_review');
});

it('parks a paid amount that differs from the intent', function () {
    checkout($this);
    [$body, $hmac] = paymobCallback(PaymentIntent::sole()->merchant_reference, 100);

    postPaymob($this, $body, $hmac);

    expect(PaymentIntent::sole()->status)->toBe(PaymentIntentStatus::NeedsReview)
        ->and(TuitionPayment::count())->toBe(0);
});

it('settles a Fawry reference from its server notification', function () {
    checkout($this, 'fawry');

    $this->postJson('/api/webhooks/payments/fawry', fawryNotification(PaymentIntent::sole()->merchant_reference, '1500.00'))->assertOk();

    expect(TuitionPayment::sole()->method)->toBe(TuitionPaymentMethod::Fawry)
        ->and($this->invoice->fresh()->status)->toBe(TuitionInvoiceStatus::Paid);
});

it('acknowledges events for unknown references without failing', function () {
    [$body, $hmac] = paymobCallback('BHG999999-01J00000000000000000000000', 100);

    postPaymob($this, $body, $hmac)->assertOk();

    expect(WebhookEvent::sole()->outcome)->toBe('unknown_reference');
});

it('keeps checkout to the payer and to plans with Auto Collection', function () {
    $coGuardian = User::factory()->create();
    $this->actingAs($coGuardian)->postJson("/api/v1/me/invoices/{$this->invoice->id}/checkout", ['gateway' => 'paymob'])->assertForbidden(); // not a member

    TenantEntitlementOverride::where('key', 'auto_collection')->update(['value' => false]);
    app(EntitlementService::class)->forget($this->tenant);

    checkout($this)->assertStatus(402);
});

it('refuses checkout on a settled invoice and for unconfigured gateways', function () {
    config(['services.fawry.secure_key' => null]);
    checkout($this, 'fawry')->assertUnprocessable()->assertJsonValidationErrors('gateway');

    $this->invoice->update(['status' => TuitionInvoiceStatus::Paid, 'paid_piasters' => 150_000]);
    checkout($this)->assertUnprocessable()->assertJsonValidationErrors('invoice');
});
