<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\RolloutFlag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

/**
 * Gradual release of V2 modules, built on Laravel Pennant with the nursery as
 * the scope. A flag is off by default; it is on for a nursery when switched on
 * for that nursery, or when it has been released to everyone (stored under the
 * PLATFORM scope). Every change is audited.
 */
class RolloutService
{
    public const PLATFORM = '__platform__';

    public function __construct(private AuditLogger $audit) {}

    /**
     * Pennant resolver: the first time a nursery is checked, it inherits the
     * platform-wide release state. The result is then stored per nursery.
     */
    public function resolve(RolloutFlag $flag, mixed $scope): bool
    {
        if (! $scope instanceof Tenant) {
            return false;
        }

        return $this->releasedToEveryone($flag);
    }

    public function active(Tenant $tenant, RolloutFlag $flag): bool
    {
        return Feature::for($tenant)->active($flag->value);
    }

    public function releasedToEveryone(RolloutFlag $flag): bool
    {
        return Feature::for(self::PLATFORM)->active($flag->value);
    }

    public function enable(Tenant $tenant, RolloutFlag $flag, User $admin): void
    {
        $this->set($tenant, $flag, true, $admin);
    }

    public function disable(Tenant $tenant, RolloutFlag $flag, User $admin): void
    {
        $this->set($tenant, $flag, false, $admin);
    }

    /**
     * Release to (or withdraw from) every nursery. Resets per-nursery choices
     * so every nursery re-resolves against the new platform state.
     */
    public function setForEveryone(RolloutFlag $flag, bool $active, User $admin): void
    {
        DB::transaction(function () use ($flag, $active, $admin) {
            Feature::purge($flag->value);

            $active
                ? Feature::for(self::PLATFORM)->activate($flag->value)
                : Feature::for(self::PLATFORM)->deactivate($flag->value);

            $this->audit->record(AuditAction::RolloutChanged, null, [
                'flag' => $flag->value,
                'scope' => 'everyone',
                'active' => $active,
            ], $admin);
        });
    }

    private function set(Tenant $tenant, RolloutFlag $flag, bool $active, User $admin): void
    {
        DB::transaction(function () use ($tenant, $flag, $active, $admin) {
            $active
                ? Feature::for($tenant)->activate($flag->value)
                : Feature::for($tenant)->deactivate($flag->value);

            $this->audit->record(AuditAction::RolloutChanged, $tenant, [
                'flag' => $flag->value,
                'scope' => 'tenant',
                'active' => $active,
            ], $admin, $tenant->id);
        });
    }
}
