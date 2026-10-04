<?php

namespace App\Policies;

use App\Models\User;
use App\Support\TenantContext;

/**
 * Only an owner/admin of the current tenant may view or change billing.
 */
class SubscriptionPolicy
{
    public function manage(User $user): bool
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant !== null && $user->manages($tenant);
    }
}
