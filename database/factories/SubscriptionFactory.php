<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ];
    }
}
