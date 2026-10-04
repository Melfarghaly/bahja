<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'number' => 'INV-'.Str::upper(Str::random(8)),
            'amount_egp' => fake()->randomElement([750, 1900, 3900]),
            'status' => InvoiceStatus::Open,
            'due_at' => now()->addDays(7),
        ];
    }
}
