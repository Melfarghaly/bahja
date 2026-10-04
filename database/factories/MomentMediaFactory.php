<?php

namespace Database\Factories;

use App\Models\Moment;
use App\Models\MomentMedia;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MomentMedia>
 */
class MomentMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'moment_id' => Moment::factory(),
            'disk' => 'local',
            'path' => 'wall/test/'.fake()->uuid().'.jpg',
            'thumb_path' => 'wall/test/'.fake()->uuid().'-thumb.jpg',
            'mime' => 'image/jpeg',
            'size' => 120_000,
            'width' => 1600,
            'height' => 1200,
        ];
    }
}
