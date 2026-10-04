<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Models\FeePlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChildFeePlan>
 */
class ChildFeePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'child_id' => Child::factory(),
            'fee_plan_id' => FeePlan::factory(),
            'starts_on' => now()->startOfMonth()->toDateString(),
        ];
    }
}
