<?php

namespace App\Services\Notifications;

use App\Enums\CustodyFlag;
use App\Enums\GuardianRole;
use App\Enums\NotificationType;
use App\Enums\RolloutFlag;
use App\Models\Attendance;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RolloutService;
use App\Support\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * "Arrived" and "picked up" pushes to the child's family (released with the
 * Messaging Hub). Drivers and emergency contacts are not family: they are not
 * told, and a custody-blocked parent never is.
 */
class AttendanceNotifications
{
    public function __construct(
        private NotificationRouter $router,
        private RolloutService $rollouts,
        private TenantContext $tenantContext,
    ) {}

    public function arrived(Child $child, Attendance $attendance): void
    {
        $tenant = $this->tenant($child);
        if ($tenant === null) {
            return;
        }

        foreach ($this->family($child) as $guardian) {
            $this->router->notify($tenant, $guardian, NotificationType::ChildArrived, [
                'child' => $child->first_name,
                'nursery' => $tenant->name,
                'time' => $this->clock($attendance->checked_in_at),
            ], $child, ['screen' => 'ward']);
        }
    }

    public function pickedUp(Child $child, Attendance $attendance, string $collectorName, ?User $collector = null): void
    {
        $tenant = $this->tenant($child);
        if ($tenant === null) {
            return;
        }

        foreach ($this->family($child)->reject(fn (User $g) => $g->id === $collector?->id) as $guardian) {
            $this->router->notify($tenant, $guardian, NotificationType::ChildPickedUp, [
                'child' => $child->first_name,
                'collector' => $collectorName,
                'nursery' => $tenant->name,
                'time' => $this->clock($attendance->checked_out_at),
            ], $child, ['screen' => 'ward']);
        }
    }

    private function tenant(Child $child): ?Tenant
    {
        $tenant = $this->tenantContext->get() ?? $child->tenant;

        return $tenant !== null && $this->rollouts->active($tenant, RolloutFlag::MessagingHub) ? $tenant : null;
    }

    /**
     * @return Collection<int, User>
     */
    private function family(Child $child): Collection
    {
        return $child->loadMissing('guardians')->guardians
            ->filter(fn (User $g) => in_array($g->pivot->role, [GuardianRole::Primary->value, GuardianRole::Viewer->value], true)
                && $g->pivot->custody_flag !== CustodyFlag::Blocked->value)
            ->values();
    }

    private function clock(?CarbonInterface $at): string
    {
        return ($at ?? now())->copy()->setTimezone(QuietHours::TIMEZONE)->format('H:i');
    }
}
