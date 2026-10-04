<?php

namespace App\Services\Payments\Checkout;

use Carbon\CarbonImmutable;

/**
 * What a gateway returns when a checkout is opened: a hosted payment URL
 * (Paymob) or a reference number to pay at an outlet (Fawry).
 */
final class CheckoutSession
{
    public function __construct(
        public readonly ?string $gatewayReference,
        public readonly ?string $url = null,
        public readonly ?string $paymentCode = null,
        public readonly ?CarbonImmutable $expiresAt = null,
    ) {}
}
