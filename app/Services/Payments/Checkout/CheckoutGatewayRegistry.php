<?php

namespace App\Services\Payments\Checkout;

use App\Enums\PaymentGatewayName;
use App\Models\Tenant;
use InvalidArgumentException;

/**
 * Resolves the gateway adapters and their credentials. Today credentials are
 * platform-wide (config/services.php); a per-nursery merchant account plugs in
 * here without touching callers, once the money-flow decision is made.
 */
class CheckoutGatewayRegistry
{
    public function for(PaymentGatewayName $name, ?Tenant $tenant = null): CheckoutGateway
    {
        return match ($name) {
            PaymentGatewayName::Paymob => new PaymobCheckoutGateway([
                'base_url' => config('services.paymob.base_url'),
                'secret_key' => config('services.paymob.secret_key'),
                'public_key' => config('services.paymob.public_key'),
                'hmac_secret' => config('services.paymob.hmac_secret'),
                'integration_ids' => array_values(array_filter(array_map('intval', explode(',', (string) config('services.paymob.integration_ids'))))),
                'expiry_minutes' => (int) config('services.paymob.expiry_minutes', 60),
            ]),
            PaymentGatewayName::Fawry => new FawryCheckoutGateway([
                'base_url' => config('services.fawry.base_url'),
                'merchant_code' => config('services.fawry.merchant_code'),
                'secure_key' => config('services.fawry.secure_key'),
                'expiry_hours' => (int) config('services.fawry.expiry_hours', 48),
            ]),
        };
    }

    /**
     * Gateways with credentials, i.e. offered to parents.
     *
     * @return array<int, PaymentGatewayName>
     */
    public function available(?Tenant $tenant = null): array
    {
        return array_values(array_filter(
            PaymentGatewayName::cases(),
            fn (PaymentGatewayName $name) => $this->for($name, $tenant)->isConfigured(),
        ));
    }

    public function forProvider(string $provider): CheckoutGateway
    {
        $name = PaymentGatewayName::tryFrom($provider)
            ?? throw new InvalidArgumentException("Unknown payment provider [{$provider}].");

        return $this->for($name);
    }
}
