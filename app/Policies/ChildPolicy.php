<?php

namespace App\Policies;

use App\Enums\CustodyFlag;
use App\Models\Child;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Authorization for children. Note the deliberate rule: pickup and wall-viewing
 * are checked at the PAIR level (this user x this child) by reading the
 * child_guardian pivot, never at the account level.
 *
 * Staff (owner/admin/teacher) may read children. Changing a child — and above
 * all its guardians, pickup permissions and custody flags — is a safety
 * decision reserved to nursery managers (owner/admin), never teachers.
 */
class ChildPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isManager($user);
    }

    public function update(User $user, Child $child): bool
    {
        return $this->isManager($user);
    }

    public function delete(User $user, Child $child): bool
    {
        return $this->isManager($user);
    }

    /**
     * The staff view of a child (all guardians, medical notes). Guardians use
     * their own, narrower endpoints under /me/wards.
     */
    public function view(User $user, Child $child): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Per-pair: may this user see this specific child's timeline wall?
     */
    public function viewWall(User $user, Child $child): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        return $child->guardians()
            ->where('guardian_id', $user->id)
            ->wherePivot('can_view_wall', true)
            ->wherePivot('custody_flag', '!=', CustodyFlag::Blocked->value)
            ->exists();
    }

    /**
     * Per-pair: is this user authorized to pick up this specific child today?
     */
    public function pickup(User $user, Child $child): bool
    {
        return $child->guardians()
            ->where('guardian_id', $user->id)
            ->wherePivot('can_pickup', true)
            ->wherePivot('custody_flag', '!=', CustodyFlag::Blocked->value)
            ->exists();
    }

    private function isManager(User $user): bool
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant !== null && $user->manages($tenant);
    }

    private function isStaff(User $user): bool
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant !== null
            && ($user->manages($tenant) || $user->teachesIn($tenant));
    }
}
