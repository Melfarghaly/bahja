<?php

namespace App\Policies;

use App\Models\Moment;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Staff post to the wall. A teacher may take back her own update within a
 * day; after that (or anyone's) only a manager may delete it.
 */
class MomentPolicy
{
    public const AUTHOR_DELETE_HOURS = 24;

    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function delete(User $user, Moment $moment): bool
    {
        $tenant = app(TenantContext::class)->get();

        if ($tenant !== null && $user->manages($tenant)) {
            return true;
        }

        return $moment->author_id === $user->id
            && $moment->created_at->gt(now()->subHours(self::AUTHOR_DELETE_HOURS))
            && $this->isStaff($user);
    }

    private function isStaff(User $user): bool
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant !== null && ($user->manages($tenant) || $user->teachesIn($tenant));
    }
}
