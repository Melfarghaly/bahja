<?php

namespace Database\Factories;

use App\Enums\DevicePlatform;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushDevice>
 */
class PushDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => DevicePlatform::Android,
            'token' => fake()->unique()->regexify('[A-Za-z0-9_-]{22}:APA91[A-Za-z0-9_-]{134}'),
            'locale' => 'ar',
            'last_seen_at' => now(),
        ];
    }
}
