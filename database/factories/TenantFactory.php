<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Nursery';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'phone' => fake()->numerify('02########'),
            'address' => fake()->address(),
            'status' => TenantStatus::Active,
        ];
    }
}
