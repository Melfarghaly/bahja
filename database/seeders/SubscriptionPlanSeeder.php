<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * The four Bahga subscription tiers (prices in EGP, monthly).
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_egp' => 0,
                'max_children' => 15,
                'max_teachers' => 3,
                'included_sms' => 0,
                'features' => ['attendance', 'wall'],
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price_egp' => 750,
                'max_children' => 40,
                'max_teachers' => 8,
                'included_sms' => 100,
                'features' => ['attendance', 'wall', 'billing', 'pickup_verification'],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_egp' => 1900,
                'max_children' => 100,
                'max_teachers' => 20,
                'included_sms' => 500,
                'features' => ['attendance', 'wall', 'billing', 'pickup_verification', 'reports'],
            ],
            [
                'name' => 'Advanced',
                'slug' => 'advanced',
                'price_egp' => 3900,
                'max_children' => null, // unlimited
                'max_teachers' => null,
                'included_sms' => 2000,
                'features' => ['attendance', 'wall', 'billing', 'pickup_verification', 'reports', 'white_label'],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], [
                ...$plan,
                'billing_cycle' => 'monthly',
                'is_active' => true,
            ]);
        }
    }
}
