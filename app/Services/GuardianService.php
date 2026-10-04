<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Child;
use App\Models\User;
use BackedEnum;

/**
 * Manages the child <-> guardian links independently of child creation, e.g.
 * adding a pickup-authorized driver to an already-enrolled child.
 */
class GuardianService
{
    public function __construct(
        private UserDirectoryService $directory,
        private AuditLogger $audit,
    ) {}

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
        $permissions = [
            'relationship' => $attributes['relationship'],
            'role' => $attributes['role'],
            'can_view_wall' => $attributes['can_view_wall'] ?? true,
            'can_pickup' => $attributes['can_pickup'] ?? false,
            'is_payer' => $attributes['is_payer'] ?? false,
            'custody_flag' => $attributes['custody_flag'] ?? 'none',
        ];

        $before = $this->currentPermissions($child, $guardian);

        $child->guardians()->syncWithoutDetaching([
            $guardian->id => ['tenant_id' => $child->tenant_id] + $permissions,
        ]);

        $this->audit->record(
            $before === null ? AuditAction::GuardianAttached : AuditAction::GuardianUpdated,
            $child,
            array_filter([
                'guardian_id' => $guardian->id,
                'before' => $before,
                'after' => $this->normalize($permissions),
            ], fn ($value) => $value !== null),
        );
    }

    public function detach(Child $child, User $guardian): void
    {
        $before = $this->currentPermissions($child, $guardian);

        if ($child->guardians()->detach($guardian->id) > 0) {
            $this->audit->record(AuditAction::GuardianDetached, $child, [
                'guardian_id' => $guardian->id,
                'before' => $before,
            ]);
        }
    }

    /**
     * The guardian's current per-pair permissions, or null when not linked.
     *
     * @return array<string, mixed>|null
     */
    private function currentPermissions(Child $child, User $guardian): ?array
    {
        $pivot = $child->guardians()->whereKey($guardian->id)->first()?->pivot;

        return $pivot === null ? null : $this->normalize($pivot->only([
            'relationship', 'role', 'can_view_wall', 'can_pickup', 'is_payer', 'custody_flag',
        ]));
    }

    /**
     * @param  array<string, mixed>  $permissions
     * @return array<string, mixed>
     */
    private function normalize(array $permissions): array
    {
        foreach (['can_view_wall', 'can_pickup', 'is_payer'] as $flag) {
            $permissions[$flag] = (bool) $permissions[$flag];
        }

        return array_map(fn ($value) => $value instanceof BackedEnum ? $value->value : $value, $permissions);
    }
}
