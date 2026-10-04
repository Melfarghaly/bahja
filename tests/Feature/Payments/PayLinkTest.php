<?php

use App\Models\PaymentIntent;
use App\Models\TenantEntitlementOverride;
use App\Services\Tuition\PayLinkService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureGateways();
    Http::fake([
        'accept.paymob.test/v1/intention/' => Http::response(['id' => 'pi_1', 'client_secret' => 'cs_1'], 201),
        'fawry.test/*' => Http::response(['statusCode' => 200, 'referenceNumber' => '966512345']),
    ]);
    [$this->tenant, $this->payer, $this->invoice] = onlinePayingFamily(150_000);
    $this->link = app(PayLinkService::class)->linkFor($this->invoice);
});

it('opens the pay page from a signed link without signing in', function () {
    $this->get($this->link)
        ->assertOk()
        ->assertSee($this->tenant->name)
        ->assertSee('1,500 ج.م')
        ->assertSee('ادفع عبر');
});

it('rejects tampered or foreign links', function () {
    $this->get(str_replace("/{$this->invoice->id}?", '/'.($this->invoice->id + 1).'?', $this->link))->assertForbidden();
    $this->get(route('pay.show', ['tenant' => $this->tenant->id, 'invoice' => $this->invoice->id]))->assertForbidden();
});

it('redirects to Paymob or shows the Fawry code', function () {
    $links = app(PayLinkService::class);

    $this->post($links->checkoutActionFor($this->invoice, 'paymob'))
        ->assertRedirect('https://accept.paymob.test/unifiedcheckout/?publicKey=pk_test&clientSecret=cs_1');

    $this->post($links->checkoutActionFor($this->invoice, 'fawry'))->assertOk()->assertSee('966512345');

    expect(PaymentIntent::pluck('payer_id')->unique()->all())->toBe([$this->payer->id]);
});

it('is unavailable when the nursery lacks Auto Collection', function () {
    TenantEntitlementOverride::where('key', 'auto_collection')->update(['value' => false]);

    $this->get($this->link)->assertNotFound();
});

it('shows a settled invoice as paid with no pay buttons', function () {
    $this->invoice->update(['status' => 'paid', 'paid_piasters' => 150_000]);

    $this->get($this->link)->assertOk()->assertSee('مدفوعة')->assertDontSee('ادفع عبر');
});
