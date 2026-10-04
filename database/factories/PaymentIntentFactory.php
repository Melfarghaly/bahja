<?php

namespace Database\Factories;

use App\Enums\PaymentGatewayName;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentIntent>
 */
class PaymentIntentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tuition_invoice_id' => TuitionInvoice::factory(),
            'payer_id' => User::factory(),
            'gateway' => PaymentGatewayName::Paymob,
            'amount_piasters' => 150_000,
            'merchant_reference' => fn (array $a) => PaymentIntent::newMerchantReference(is_int($a['tenant_id']) ? $a['tenant_id'] : 0),
            'status' => PaymentIntentStatus::Pending,
            'expires_at' => now()->addDay(),
        ];
    }
}
