<?php

namespace App\Services;

use App\Models\Child;
use App\Support\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for enrolling and updating children, including wiring up the
 * child <-> guardian relationships with their per-pair permissions.
 *
 * A guardian entry may be provided either as an existing `user_id` (API) or as
 * a `name` + `phone` pair (nursery web UI), in which case the user is resolved
 * or created via the user directory.
 */
class ChildService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private UserDirectoryService $directory,
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Child
    {
        return DB::transaction(function () use ($data) {
            $tenant = $this->tenantContext->get();

            // Enforce the plan quota before creating anything.
            $this->subscriptions->assertCanAddChild($tenant);

            $child = Child::create(Arr::except($data, ['guardians']));

            $this->syncGuardians($child, $data['guardians'] ?? []);

            return $child->load(['classroom', 'guardians']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Child $child, array $data): Child
    {
        return DB::transaction(function () use ($child, $data) {
            $child->update(Arr::except($data, ['guardians']));

            if (array_key_exists('guardians', $data)) {
                $this->syncGuardians($child, $data['guardians'], replace: true);
            }

            return $child->load(['classroom', 'guardians']);
        });
    }

    /**
     * Attach (or replace) guardians with their pivot permissions.
     *
     * @param  array<int, array<string, mixed>>  $guardians
     */
    private function syncGuardians(Child $child, array $guardians, bool $replace = false): void
    {
        $payload = [];

        foreach ($guardians as $guardian) {
            $userId = $this->resolveGuardianId($guardian);

            $payload[$userId] = [
                'tenant_id' => $child->tenant_id,
                'relationship' => $guardian['relationship'],
                'role' => $guardian['role'],
                'can_view_wall' => $guardian['can_view_wall'] ?? true,
                'can_pickup' => $guardian['can_pickup'] ?? false,
                'is_payer' => $guardian['is_payer'] ?? false,
                'custody_flag' => $guardian['custody_flag'] ?? 'none',
            ];
        }

        $replace
            ? $child->guardians()->sync($payload)
            : $child->guardians()->syncWithoutDetaching($payload);
    }

    /**
     * @param  array<string, mixed>  $guardian
     */
    private function resolveGuardianId(array $guardian): int
    {
        if (! empty($guardian['user_id'])) {
            return (int) $guardian['user_id'];
        }

        return $this->directory->findOrCreateByPhone($guardian['phone'], [
            'name' => $guardian['name'] ?? null,
        ])->id;
    }
}
