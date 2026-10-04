<?php

namespace Database\Seeders;

use App\Enums\Feature;
use App\Models\SubscriptionPlan;
use App\Support\Entitlements\PlanCatalog;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * The standard Bahga tiers, from the single source of truth in PlanCatalog.
     */
    public function run(): void
    {
        foreach (PlanCatalog::tiers() as $slug => $tier) {
            SubscriptionPlan::updateOrCreate(['slug' => $slug], [
                ...$tier,
                'features' => array_map(fn (Feature $feature) => $feature->value, $tier['features']),
                'limits' => $tier['limits'] ?: null,
                'billing_cycle' => 'monthly',
                'is_active' => true,
            ]);
        }
    }
}
