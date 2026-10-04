<?php

namespace App\Policies;

use App\Models\User;
use App\Support\TenantContext;

/**
 * Only staff of the current tenant may record check-in/check-out.
 */
class AttendancePolicy
{
    public function record(User $user): bool
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant !== null
            && ($user->manages($tenant) || $user->teachesIn($tenant));
    }
}
