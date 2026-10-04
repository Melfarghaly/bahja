<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Holds the current tenant for the lifetime of a request (or a console/job run).
 * Bound as a singleton so the global TenantScope and BelongsToTenant trait can
 * resolve the active tenant without threading it through every method.
 *
 * Setting the tenant also pins the database session to it, so PostgreSQL
 * Row-Level Security enforces the same boundary as the TenantScope.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function __construct(private RowLevelSecurity $rls) {}

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->rls->setTenant($tenant->id);
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->rls->setTenant(null);
    }
}
