<?php

namespace Database\Factories;

use App\Enums\FeeFrequency;
use App\Models\FeePlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeePlan>
 */
class FeePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'المصروفات الشهرية',
            'amount_piasters' => 150_000,
            'frequency' => FeeFrequency::Monthly,
            'is_active' => true,
        ];
    }
}
