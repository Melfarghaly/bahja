<?php

namespace Database\Factories;

use App\Enums\MomentType;
use App\Models\Moment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Moment>
 */
class MomentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => MomentType::Note,
            'body' => 'يوم جميل في الحضانة',
            'children_count' => 1,
            'published_at' => now(),
        ];
    }
}
