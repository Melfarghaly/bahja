<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\DiscountValueType;
use App\Models\FeeDiscount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeDiscount>
 */
class FeeDiscountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'خصم الإخوة',
            'type' => DiscountType::Sibling,
            'value_type' => DiscountValueType::Percent,
            'value' => 1000,
            'is_active' => true,
        ];
    }
}
