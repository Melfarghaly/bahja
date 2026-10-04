<?php

namespace App\Support\Entitlements;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Services\Exceptions\FeatureNotEntitledException;
use App\Services\Exceptions\PlanLimitException;

/**
 * The resolved, immutable answer to "what may this nursery do right now?".
 * Precedence: override > (plan ∪ add-ons). A null limit means unlimited.
 */
final class TenantEntitlements
{
    /**
     * @param  array<int, Feature>  $features  plan features plus add-on grants
     * @param  array<string, ?int>  $limits  plan limits keyed by Limit value, already extended by add-ons
     * @param  array<string, mixed>  $overrides  keyed by Feature/Limit value
     */
    public function __construct(
        public readonly string $planName,
        private array $features,
        private array $limits,
        private array $overrides = [],
    ) {}

    public function allows(Feature $feature): bool
    {
        if (array_key_exists($feature->value, $this->overrides)) {
            return (bool) $this->overrides[$feature->value];
        }

        return in_array($feature, $this->features, true);
    }

    public function limit(Limit $limit): ?int
    {
        if (array_key_exists($limit->value, $this->overrides)) {
            $value = $this->overrides[$limit->value];

            return $value === null ? null : (int) $value;
        }

        return $this->limits[$limit->value] ?? null;
    }

    public function isUnlimited(Limit $limit): bool
    {
        return $this->limit($limit) === null;
    }

    /**
     * How many more units may be added on top of the current usage.
     */
    public function remaining(Limit $limit, int $usage): ?int
    {
        $max = $this->limit($limit);

        return $max === null ? null : max(0, $max - $usage);
    }

    /**
     * @throws FeatureNotEntitledException
     */
    public function assertAllows(Feature $feature): void
    {
        if (! $this->allows($feature)) {
            throw new FeatureNotEntitledException($feature);
        }
    }

    /**
     * Guard before adding one more unit on top of the current usage.
     *
     * @throws PlanLimitException
     */
    public function assertCanAdd(Limit $limit, int $usage): void
    {
        $max = $this->limit($limit);

        if ($max !== null && $usage >= $max) {
            throw new PlanLimitException($limit->value, $max);
        }
    }

    /**
     * Enabled features, for display.
     *
     * @return array<int, Feature>
     */
    public function features(): array
    {
        return array_values(array_filter(Feature::cases(), fn (Feature $f) => $this->allows($f)));
    }
}
