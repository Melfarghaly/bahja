<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoice = Invoice::factory()->create();

        return [
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'amount_egp' => $invoice->amount_egp,
            'status' => PaymentStatus::Pending,
            'gateway' => 'paymob',
        ];
    }
}
