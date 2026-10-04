<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\LatePickupAlert;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LatePickupAlert>
 */
class LatePickupAlertFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'child_id' => Child::factory(),
            'date' => now()->toDateString(),
            'stage' => LatePickupAlert::GUARDIANS,
        ];
    }
}
