<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'price_egp' => fake()->randomElement([0, 750, 1900, 3900]),
            'billing_cycle' => BillingCycle::Monthly,
            'max_children' => fake()->randomElement([15, 40, 100, null]),
            'max_teachers' => fake()->randomElement([3, 8, 20, null]),
            'included_sms' => fake()->randomElement([0, 100, 500]),
            'features' => ['attendance', 'wall', 'billing'],
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'name' => 'Free',
            'slug' => 'free',
            'price_egp' => 0,
            'max_children' => 15,
            'max_teachers' => 3,
        ]);
    }
}
