<?php

namespace App\Services\Admin;

use App\Models\SubscriptionPlan;
use App\Services\Exceptions\PlanInUseException;

/**
 * CRUD for subscription plans (the global pricing catalog).
 */
class PlanService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::create($this->normalize($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        $plan->update($this->normalize($data));

        return $plan;
    }

    /**
     * @throws PlanInUseException when the plan still has subscriptions attached.
     */
    public function delete(SubscriptionPlan $plan): void
    {
        if ($plan->subscriptions()->exists()) {
            throw new PlanInUseException($plan->name);
        }

        $plan->delete();
    }

    /**
     * Normalize "unlimited" empty inputs to null and booleans/features to a clean shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $data['max_children'] = ($data['max_children'] ?? null) ?: null;
        $data['max_teachers'] = ($data['max_teachers'] ?? null) ?: null;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['features'] = $data['features'] ?? [];

        return $data;
    }
}
