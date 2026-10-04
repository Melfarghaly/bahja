<?php

namespace Database\Factories;

use App\Enums\ConsentScope;
use App\Models\Child;
use App\Models\MediaConsent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaConsent>
 */
class MediaConsentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'child_id' => Child::factory(),
            'scope' => ConsentScope::Wall,
            'granted_at' => now(),
        ];
    }
}
