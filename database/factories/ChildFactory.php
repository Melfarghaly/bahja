<?php

namespace Database\Factories;

use App\Enums\ChildStatus;
use App\Enums\Gender;
use App\Models\Child;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Child>
 */
class ChildFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'classroom_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'status' => ChildStatus::Active,
        ];
    }
}
