<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Sunflowers', 'Butterflies', 'Ducklings', 'Stars']),
            'capacity' => fake()->numberBetween(10, 25),
        ];
    }
}
