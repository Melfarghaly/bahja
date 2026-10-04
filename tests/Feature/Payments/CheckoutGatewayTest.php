<?php

use App\Enums\PaymentGatewayName;
use App\Models\PaymentIntent;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\Payments\Checkout\CheckoutGatewayRegistry;
use App\Services\Payments\Exceptions\GatewayException;
use App\Services\Payments\Exceptions\InvalidWebhookSignature;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => configureGateways());

function gateway(PaymentGatewayName $name)
{
    return app(CheckoutGatewayRegistry::class)->for($name);
}

function webhookRequest(array $body, array $query = []): Request
{
    return Request::create('/api/webhooks/payments/x?'.http_build_query($query), 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body));
}

it('opens a Paymob intention in piasters and returns the unified checkout URL', function () {
    Http::fake(['accept.paymob.test/v1/intention/' => Http::response(['id' => 'pi_123', 'client_secret' => 'cs_abc'], 201)]);
    $payer = User::factory()->create(['name' => 'منى عبد الله', 'phone' => '01011112222']);
    $intent = PaymentIntent::factory()->make(['amount_piasters' => 185_050, 'merchant_reference' => 'BHG1-01J00000000000000000000000']);

    $session = gateway(PaymentGatewayName::Paymob)->createCheckout($intent, TuitionInvoice::factory()->make(['number' => 'INV-2026-000001']), $payer);

    expect($session->gatewayReference)->toBe('pi_123')
        ->and($session->url)->toBe('https://accept.paymob.test/unifiedcheckout/?publicKey=pk_test&clientSecret=cs_abc');

    Http::assertSent(fn (ClientRequest $r) => $r->hasHeader('Authorization', 'Token sk_test')
        && $r['amount'] === 185_050
        && $r['payment_methods'] === [111, 222]
        && $r['special_reference'] === 'BHG1-01J00000000000000000000000'
        && $r['billing_data']['first_name'] === 'منى');
});

it('surfaces Paymob errors as gateway exceptions', function () {
    Http::fake(['*' => Http::response(['detail' => 'bad key'], 401)]);

    gateway(PaymentGatewayName::Paymob)->createCheckout(PaymentIntent::factory()->make(), TuitionInvoice::factory()->make(), User::factory()->make());
})->throws(GatewayException::class);

it('accepts a Paymob callback with a valid HMAC and rejects a tampered one', function () {
    [$body, $hmac] = paymobCallback('BHG1-01J00000000000000000000000', 150_000);

    $event = gateway(PaymentGatewayName::Paymob)->parseWebhook(webhookRequest($body, ['hmac' => $hmac]));
    expect($event->successful)->toBeTrue()
        ->and($event->final)->toBeTrue()
        ->and($event->amountPiasters)->toBe(150_000)
        ->and($event->merchantReference)->toBe('BHG1-01J00000000000000000000000')
        ->and($event->eventId)->toBe('txn:9001');

    $body['obj']['amount_cents'] = 1;   // attacker lowers the amount
    expect(fn () => gateway(PaymentGatewayName::Paymob)->parseWebhook(webhookRequest($body, ['hmac' => $hmac])))
        ->toThrow(InvalidWebhookSignature::class);
});

it('creates a Fawry reference with the documented signature and exact 2-decimal amount', function () {
    Http::fake(['fawry.test/*' => Http::response(['type' => 'ChargeResponse', 'statusCode' => 200, 'referenceNumber' => '966512345'])]);
    $payer = User::factory()->create();
    $intent = PaymentIntent::factory()->make(['amount_piasters' => 150_050, 'merchant_reference' => 'BHG1-REF']);

    $session = gateway(PaymentGatewayName::Fawry)->createCheckout($intent, TuitionInvoice::factory()->make(), $payer);

    expect($session->paymentCode)->toBe('966512345')->and($session->url)->toBeNull();
    Http::assertSent(fn (ClientRequest $r) => $r['amount'] === '1500.50'
        && $r['paymentMethod'] === 'PAYATFAWRY'
        && $r['signature'] === hash('sha256', 'MERCHANT'.'BHG1-REF'.$payer->id.'PAYATFAWRY'.'1500.50'.'secure_test'));
});

it('verifies Fawry notifications and matches on the order amount, not the fee-inclusive one', function () {
    $event = gateway(PaymentGatewayName::Fawry)->parseWebhook(webhookRequest(fawryNotification('BHG1-REF', '1500.50')));

    expect($event->successful)->toBeTrue()
        ->and($event->amountPiasters)->toBe(150_050)
        ->and($event->eventId)->toBe('966512345:PAID');

    $forged = fawryNotification('BHG1-REF', '1500.50');
    $forged['orderStatus'] = 'PAID';
    $forged['orderAmount'] = '1.00';

    expect(fn () => gateway(PaymentGatewayName::Fawry)->parseWebhook(webhookRequest($forged)))->toThrow(InvalidWebhookSignature::class);
});

it('offers only gateways that have credentials', function () {
    config(['services.fawry.secure_key' => null]);

    expect(app(CheckoutGatewayRegistry::class)->available())->toBe([PaymentGatewayName::Paymob]);
});

it('round-trips the nursery id through the merchant reference', function () {
    $reference = PaymentIntent::newMerchantReference(42);

    expect(PaymentIntent::tenantIdFromReference($reference))->toBe(42)
        ->and(PaymentIntent::tenantIdFromReference('BHG42-short'))->toBeNull()
        ->and(PaymentIntent::tenantIdFromReference('something-else'))->toBeNull();
});
