<?php

namespace App\Services\Pickup;

use App\Enums\AuditAction;
use App\Enums\CustodyFlag;
use App\Enums\MemberType;
use App\Enums\NotificationType;
use App\Models\Attendance;
use App\Models\Child;
use App\Models\LatePickupAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationRouter;
use Carbon\CarbonImmutable;

/**
 * Children still at the nursery after its pickup deadline: guardians who may
 * collect them are alerted after GUARDIAN_GRACE minutes, the nursery managers
 * after MANAGER_GRACE minutes (push, or SMS when no app is reachable). Each
 * stage fires once per child per day.
 */
class LatePickupService
{
    public const TIMEZONE = 'Africa/Cairo';

    public const GUARDIAN_GRACE = 15;

    public const MANAGER_GRACE = 45;

    public function __construct(
        private NotificationRouter $router,
        private AuditLogger $audit,
    ) {}

    /**
     * The nursery's deadline today (Cairo time), or null when not configured.
     */
    public function deadline(Tenant $tenant, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        $time = $tenant->settings['pickup_deadline'] ?? null;

        if (! is_string($time) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return null;
        }

        $local = ($now ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE);

        return CarbonImmutable::parse($local->toDateString().' '.$time, self::TIMEZONE);
    }

    /**
     * @return array{guardians: int, managers: int}
     */
    public function run(Tenant $tenant, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $deadline = $this->deadline($tenant, $now);
        $sent = ['guardians' => 0, 'managers' => 0];

        if ($deadline === null || $now->lt($deadline->addMinutes(self::GUARDIAN_GRACE))) {
            return $sent;
        }

        $stillHere = Attendance::where('tenant_id', $tenant->id)
            ->whereDate('date', $deadline->toDateString())
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->with(['child' => fn ($q) => $q->with('guardians')])
            ->get();

        foreach ($stillHere as $attendance) {
            $child = $attendance->child;

            if ($this->reserve($tenant, $child, $deadline, LatePickupAlert::GUARDIANS)) {
                $sent['guardians'] += $this->alertGuardians($tenant, $child, $deadline);
            }

            if ($now->gte($deadline->addMinutes(self::MANAGER_GRACE))
                && $this->reserve($tenant, $child, $deadline, LatePickupAlert::MANAGERS)) {
                $sent['managers'] += $this->alertManagers($tenant, $child, $deadline);
            }
        }

        return $sent;
    }

    private function reserve(Tenant $tenant, Child $child, CarbonImmutable $deadline, string $stage): bool
    {
        return LatePickupAlert::query()->insertOrIgnore([
            'tenant_id' => $tenant->id,
            'child_id' => $child->id,
            'date' => $deadline->toDateString(),
            'stage' => $stage,
            'created_at' => now(),
        ]) === 1;
    }

    private function alertGuardians(Tenant $tenant, Child $child, CarbonImmutable $deadline): int
    {
        // Those who can come and get the child (the router skips custody blocks too).
        $recipients = $child->guardians
            ->filter(fn (User $g) => $g->pivot->can_pickup && $g->pivot->custody_flag !== CustodyFlag::Blocked->value);

        $notified = $recipients->filter(fn (User $guardian) => $this->router->notify(
            $tenant, $guardian, NotificationType::LatePickup, $this->params($tenant, $child, $deadline), $child, ['screen' => 'ward'],
        ) !== null)->count();

        $this->finish($child, $deadline, LatePickupAlert::GUARDIANS, $notified);

        return $notified;
    }

    private function alertManagers(Tenant $tenant, Child $child, CarbonImmutable $deadline): int
    {
        $managers = $tenant->members()
            ->wherePivotIn('member_type', [MemberType::Owner->value, MemberType::Admin->value])
            ->get();

        foreach ($managers as $manager) {
            $this->router->notify($tenant, $manager, NotificationType::LatePickupManagers, $this->params($tenant, $child, $deadline), $child, ['screen' => 'attendance']);
        }

        $this->audit->record(AuditAction::LatePickupAlerted, $child, [
            'deadline' => $deadline->format('H:i'),
            'managers_notified' => $managers->count(),
        ], tenantId: $tenant->id);

        $this->finish($child, $deadline, LatePickupAlert::MANAGERS, $managers->count());

        return $managers->count();
    }

    private function finish(Child $child, CarbonImmutable $deadline, string $stage, int $recipients): void
    {
        LatePickupAlert::where('child_id', $child->id)
            ->whereDate('date', $deadline->toDateString())
            ->where('stage', $stage)
            ->update(['recipients' => $recipients]);
    }

    /**
     * @return array<string, string>
     */
    private function params(Tenant $tenant, Child $child, CarbonImmutable $deadline): array
    {
        return ['nursery' => $tenant->name, 'child' => $child->first_name, 'deadline' => $deadline->format('H:i')];
    }
}
