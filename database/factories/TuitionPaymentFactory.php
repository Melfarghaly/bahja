<?php

namespace Database\Factories;

use App\Enums\TuitionPaymentMethod;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\TuitionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TuitionPayment>
 */
class TuitionPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tuition_invoice_id' => TuitionInvoice::factory(),
            'receipt_number' => 'RCT-'.fake()->unique()->numerify('######'),
            'method' => TuitionPaymentMethod::Cash,
            'amount_piasters' => 50_000,
            'paid_at' => now(),
        ];
    }
}
