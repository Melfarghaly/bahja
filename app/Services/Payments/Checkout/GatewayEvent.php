<?php

namespace App\Services\Payments\Checkout;

/**
 * A verified webhook, normalized across gateways. `merchantReference` is null
 * for events we don't act on (e.g. non-transaction notifications).
 */
final class GatewayEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly ?string $merchantReference,
        public readonly bool $successful,
        public readonly bool $final,
        public readonly int $amountPiasters,
        public readonly ?string $gatewayReference,
    ) {}
}
