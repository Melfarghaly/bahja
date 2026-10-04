<?php

namespace Database\Factories;

use App\Enums\Feature;
use App\Models\Tenant;
use App\Models\TenantEntitlementOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantEntitlementOverride>
 */
class TenantEntitlementOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => Feature::WhiteLabel->value,
            'value' => true,
            'reason' => 'Founders deal',
        ];
    }
}
