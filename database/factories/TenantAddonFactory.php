<?php

namespace Database\Factories;

use App\Enums\Addon;
use App\Models\Tenant;
use App\Models\TenantAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantAddon>
 */
class TenantAddonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'addon' => Addon::ExtraStaff,
            'quantity' => 1,
        ];
    }
}
