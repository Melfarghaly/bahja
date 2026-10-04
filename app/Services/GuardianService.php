<?php

namespace App\Services;

use App\Models\Child;
use App\Models\User;

/**
 * Manages the child <-> guardian links independently of child creation, e.g.
 * adding a pickup-authorized driver to an already-enrolled child.
 */
class GuardianService
{
    public function __construct(private UserDirectoryService $directory) {}

    /**
     * Attach a guardian provided as name + phone (nursery web UI). Resolves or
     * creates the underlying global user, then links with pivot permissions.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function attachByInput(Child $child, array $attributes): void
    {
        $guardian = $this->directory->findOrCreateByPhone($attributes['phone'], [
            'name' => $attributes['name'] ?? null,
        ]);

        $this->attach($child, $guardian, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attach(Child $child, User $guardian, array $attributes): void
    {
        $child->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'tenant_id' => $child->tenant_id,
                'relationship' => $attributes['relationship'],
                'role' => $attributes['role'],
                'can_view_wall' => $attributes['can_view_wall'] ?? true,
                'can_pickup' => $attributes['can_pickup'] ?? false,
                'is_payer' => $attributes['is_payer'] ?? false,
                'custody_flag' => $attributes['custody_flag'] ?? 'none',
            ],
        ]);
    }

    public function detach(Child $child, User $guardian): void
    {
        $child->guardians()->detach($guardian->id);
    }
}
