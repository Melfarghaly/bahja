<?php

namespace App\Services;

use App\Enums\Addon;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\TeacherStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\TenantEntitlementOverride;
use App\Support\Entitlements\PlanCatalog;
use App\Support\Entitlements\TenantEntitlements;

/**
 * Resolves what a nursery is entitled to: its plan's features and limits,
 * extended by active add-ons, with per-tenant overrides taking precedence.
 * A nursery without an active subscription falls back to the free tier.
 *
 * Bound as a scoped singleton: results are memoized per request / job.
 */
class EntitlementService
{
    /**
     * @var array<int, TenantEntitlements>
     */
    private array $resolved = [];

    public function for(Tenant $tenant): TenantEntitlements
    {
        return $this->resolved[$tenant->id] ??= $this->resolve($tenant);
    }

    /**
     * Drop the memoized result after changing a plan, add-on or override.
     */
    public function forget(Tenant $tenant): void
    {
        unset($this->resolved[$tenant->id]);
    }

    /**
     * Current consumption of a countable limit.
     */
    public function usage(Tenant $tenant, Limit $limit): int
    {
        return match ($limit) {
            Limit::Children => $tenant->children()->count(),
            Limit::Staff => $tenant->teachers()
                ->wherePivot('status', '!=', TeacherStatus::Inactive->value)
                ->count(),
            // Per-day / per-period limits are metered by their own modules.
            Limit::DailyPhotosPerChild, Limit::MediaRetentionDays => 0,
        };
    }

    private function resolve(Tenant $tenant): TenantEntitlements
    {
        $plan = $tenant->activeSubscription()->with('plan')->first()?->plan;
        [$planName, $features, $limits] = $plan instanceof SubscriptionPlan
            ? [$plan->name, $plan->featureList(), $plan->limitMap()]
            : $this->freeTier();

        $addons = TenantAddon::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->get();

        foreach ($addons as $addon) {
            /** @var Addon $type */
            $type = $addon->addon;

            array_push($features, ...$type->grants());

            foreach ($type->extends() as $limit => $perUnit) {
                // An unlimited quota stays unlimited.
                if (array_key_exists($limit, $limits) && $limits[$limit] !== null) {
                    $limits[$limit] += $perUnit * $addon->quantity;
                }
            }
        }

        $overrides = TenantEntitlementOverride::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->pluck('value', 'key')
            ->all();

        return new TenantEntitlements($planName, array_values(array_unique($features, SORT_REGULAR)), $limits, $overrides);
    }

    /**
     * @return array{0: string, 1: array<int, Feature>, 2: array<string, ?int>}
     */
    private function freeTier(): array
    {
        $free = PlanCatalog::free();

        return [
            $free['name'],
            $free['features'],
            [
                Limit::Children->value => $free['max_children'],
                Limit::Staff->value => $free['max_teachers'],
                ...$free['limits'],
            ],
        ];
    }
}
