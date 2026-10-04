<?php

namespace Database\Factories;

use App\Enums\CouponDuration;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('BAHGA-####')),
            'name' => 'Test coupon',
            'percent_off' => 20,
            'duration' => CouponDuration::Forever,
            'is_active' => true,
        ];
    }
}
