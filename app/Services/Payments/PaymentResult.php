<?php

namespace App\Services\Payments;

/**
 * Outcome of a charge attempt, normalized across gateways.
 */
class PaymentResult
{
    public function __construct(
        public bool $successful,
        public string $reference,
        public ?string $message = null,
    ) {}
}
