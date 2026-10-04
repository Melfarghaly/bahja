<?php

namespace App\Services\Admin;

use App\Enums\TenantStatus;
use App\Models\Tenant;

/**
 * Super-admin actions on a nursery (tenant).
 */
class NurseryAdminService
{
    public function suspend(Tenant $tenant): Tenant
    {
        $tenant->update(['status' => TenantStatus::Suspended]);

        return $tenant;
    }

    public function activate(Tenant $tenant): Tenant
    {
        $tenant->update(['status' => TenantStatus::Active]);

        return $tenant;
    }
}
