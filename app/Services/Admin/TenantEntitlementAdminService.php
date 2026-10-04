<?php

namespace App\Services\Admin;

use App\Enums\Addon;
use App\Enums\AuditAction;
use App\Enums\Feature;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\TenantEntitlementOverride;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EntitlementService;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin management of a nursery's add-ons and entitlement overrides
 * (founder deals, enterprise contracts). Every change is audited.
 */
class TenantEntitlementAdminService
{
    public function __construct(
        private EntitlementService $entitlements,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{key: string, value: mixed, reason: string, expires_at?: ?string}  $data
     */
    public function grantOverride(Tenant $tenant, array $data, User $admin): TenantEntitlementOverride
    {
        return DB::transaction(function () use ($tenant, $data, $admin) {
            $value = Feature::tryFrom($data['key']) !== null
                ? $data['value'] === 'on'
                : ($data['value'] === null || $data['value'] === '' ? null : (int) $data['value']);

            $override = $tenant->entitlementOverrides()->updateOrCreate(
                ['key' => $data['key']],
                [
                    'value' => $value,
                    'reason' => $data['reason'],
                    'expires_at' => $data['expires_at'] ?? null,
                    'granted_by' => $admin->id,
                ],
            );

            $this->audit->record(AuditAction::EntitlementOverrideGranted, $tenant, [
                'key' => $data['key'],
                'value' => $value,
                'reason' => $data['reason'],
                'expires_at' => $data['expires_at'] ?? null,
            ], $admin, $tenant->id);

            $this->entitlements->forget($tenant);

            return $override;
        });
    }

    public function revokeOverride(Tenant $tenant, TenantEntitlementOverride $override, User $admin): void
    {
        DB::transaction(function () use ($tenant, $override, $admin) {
            $override->delete();

            $this->audit->record(AuditAction::EntitlementOverrideRevoked, $tenant, [
                'key' => $override->key,
                'value' => $override->value,
            ], $admin, $tenant->id);

            $this->entitlements->forget($tenant);
        });
    }

    /**
     * @param  array{addon: string, quantity: int, ends_at?: ?string}  $data
     */
    public function addAddon(Tenant $tenant, array $data, User $admin): TenantAddon
    {
        return DB::transaction(function () use ($tenant, $data, $admin) {
            $addon = $tenant->addons()->create([
                'addon' => Addon::from($data['addon']),
                'quantity' => $data['quantity'],
                'starts_at' => now(),
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            $this->audit->record(AuditAction::AddonAdded, $tenant, [
                'addon' => $data['addon'],
                'quantity' => (int) $data['quantity'],
                'ends_at' => $data['ends_at'] ?? null,
            ], $admin, $tenant->id);

            $this->entitlements->forget($tenant);

            return $addon;
        });
    }

    public function removeAddon(Tenant $tenant, TenantAddon $addon, User $admin): void
    {
        DB::transaction(function () use ($tenant, $addon, $admin) {
            $addon->delete();

            $this->audit->record(AuditAction::AddonRemoved, $tenant, [
                'addon' => $addon->addon->value,
                'quantity' => $addon->quantity,
            ], $admin, $tenant->id);

            $this->entitlements->forget($tenant);
        });
    }
}
