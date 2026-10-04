<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\PaymentGatewayName;
use App\Enums\RolloutFlag;
use App\Models\TuitionInvoice;
use App\Services\EntitlementService;
use App\Services\Payments\Checkout\CheckoutGatewayRegistry;
use App\Services\RolloutService;
use App\Services\Tuition\OnlinePaymentService;
use App\Services\Tuition\PayLinkService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Public, signed pay page reached from an SMS reminder. Shows only what a
 * parent needs to pay (nursery, invoice number, month, amount) — no child
 * data — and hands off to the gateway.
 */
class PayController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private EntitlementService $entitlements,
        private RolloutService $rollouts,
        private CheckoutGatewayRegistry $gateways,
    ) {}

    public function show(int $tenant, TuitionInvoice $invoice, PayLinkService $links): View
    {
        $this->assertOnlinePaymentEnabled();

        $gateways = $invoice->status->isCollectible() ? $this->gateways->available() : [];

        return view('pay.show', [
            'tenant' => $this->tenantContext->get(),
            'invoice' => $invoice,
            'options' => array_map(fn (PaymentGatewayName $g) => [
                'gateway' => $g,
                'action' => $links->checkoutActionFor($invoice, $g->value),
            ], $gateways),
        ]);
    }

    public function checkout(int $tenant, TuitionInvoice $invoice, string $gateway, OnlinePaymentService $online): RedirectResponse|View
    {
        $this->assertOnlinePaymentEnabled();

        $name = PaymentGatewayName::tryFrom($gateway) ?? abort(404);
        $intent = $online->startCheckout($invoice, $invoice->payer, $name);

        return $intent->checkout_url !== null
            ? redirect()->away($intent->checkout_url)
            : view('pay.code', ['tenant' => $this->tenantContext->get(), 'invoice' => $invoice, 'intent' => $intent]);
    }

    public function returned(): View
    {
        return view('pay.returned');
    }

    private function assertOnlinePaymentEnabled(): void
    {
        $tenant = $this->tenantContext->get();

        abort_unless(
            $this->rollouts->active($tenant, RolloutFlag::BahgaPay)
                && $this->entitlements->for($tenant)->allows(Feature::AutoCollection),
            404,
        );
    }
}
