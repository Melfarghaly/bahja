<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\Moment;
use App\Models\MomentChild;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MomentChild>
 */
class MomentChildFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'moment_id' => Moment::factory(),
            'child_id' => Child::factory(),
        ];
    }
}
