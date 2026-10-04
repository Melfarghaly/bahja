<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\PickupPass;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PickupPass>
 */
class PickupPassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'child_id' => Child::factory(),
            'issued_by' => User::factory(),
            'name' => 'سائق العائلة',
            'phone' => '01055554444',
            'code_hash' => hash('sha256', (string) fake()->unique()->numberBetween(100000, 999999)),
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addHours(4),
        ];
    }
}
