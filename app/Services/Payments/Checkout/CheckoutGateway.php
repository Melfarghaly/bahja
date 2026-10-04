<?php

namespace App\Services\Payments\Checkout;

use App\Enums\PaymentGatewayName;
use App\Models\PaymentIntent;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\Payments\Exceptions\GatewayException;
use App\Services\Payments\Exceptions\InvalidWebhookSignature;
use Illuminate\Http\Request;

/**
 * A gateway families can pay tuition through. Adapters only translate to and
 * from the gateway's API; all business decisions stay in the services.
 */
interface CheckoutGateway
{
    public function name(): PaymentGatewayName;

    public function isConfigured(): bool;

    /**
     * @throws GatewayException
     */
    public function createCheckout(PaymentIntent $intent, TuitionInvoice $invoice, User $payer): CheckoutSession;

    /**
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): GatewayEvent;
}
