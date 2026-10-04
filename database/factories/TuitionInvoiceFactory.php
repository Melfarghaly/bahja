<?php

namespace Database\Factories;

use App\Enums\TuitionInvoiceStatus;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TuitionInvoice>
 */
class TuitionInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'number' => 'INV-'.fake()->unique()->numerify('######'),
            'payer_id' => User::factory(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(7)->toDateString(),
            'subtotal_piasters' => 150_000,
            'discount_piasters' => 0,
            'total_piasters' => 150_000,
            'status' => TuitionInvoiceStatus::Open,
        ];
    }
}
