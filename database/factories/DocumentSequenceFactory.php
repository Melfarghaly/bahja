<?php

namespace Database\Factories;

use App\Models\DocumentSequence;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSequence>
 */
class DocumentSequenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => 'INV-'.fake()->unique()->numberBetween(2000, 9999),
            'last_value' => 0,
        ];
    }
}
