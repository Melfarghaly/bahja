<?php

namespace App\Services;

use App\Enums\AttendanceMethod;
use App\Enums\AuditAction;
use App\Enums\PickupMethod;
use App\Models\Attendance;
use App\Models\Child;
use App\Models\PickupPass;
use App\Models\User;
use App\Services\Pickup\PickupVerificationService;
use App\Support\Pickup\PickupCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Records check-in and check-out, and verifies that the person collecting a
 * child is authorized to do so (pickup verification — the core safety feature).
 * Every pickup attempt, allowed or refused, lands in the audit trail.
 */
class AttendanceService
{
    public function __construct(
        private AuditLogger $audit,
        private PickupVerificationService $verification,
    ) {}

    /**
     * @param  CarbonImmutable|null  $at  when the device recorded it (offline sync); defaults to now
     */
    public function checkIn(Child $child, User $by, AttendanceMethod $method = AttendanceMethod::Qr, ?CarbonImmutable $at = null): Attendance
    {
        $at ??= CarbonImmutable::now();

        return Attendance::updateOrCreate(
            ['child_id' => $child->id, 'date' => $at->toDateString()],
            [
                'tenant_id' => $child->tenant_id,
                'checked_in_at' => $at,
                'checked_in_by' => $by->id,
                'check_in_method' => $method,
            ],
        );
    }

    /**
     * Check a child out after verifying the collector (a guardian, a guardian's
     * rotating QR, or a one-time pass).
     *
     * @throws AuthorizationException when the collector may not take this child.
     */
    public function checkOut(Child $child, User|PickupCandidate $collector, ?User $staff = null): Attendance
    {
        $candidate = $collector instanceof User ? $this->verification->forGuardian($collector) : $collector;
        $refusal = $this->verification->refusal($candidate, $child);

        if ($refusal !== null) {
            $this->audit->record(AuditAction::PickupDenied, $child, $this->trail($candidate) + ['reason' => $refusal], $staff);

            throw new AuthorizationException(__('api.errors.pickup_not_authorized').' ('.__('pickup.reasons.'.$refusal).')');
        }

        return DB::transaction(function () use ($child, $candidate, $staff) {
            if ($candidate->pass !== null) {
                // Single use, even if two teachers scan the same code at once.
                $pass = PickupPass::whereKey($candidate->pass->id)->lockForUpdate()->first();
                if ($pass->used_at !== null) {
                    throw new AuthorizationException(__('pickup.invalid_pass'));
                }
                $pass->update(['used_at' => now()]);
            }

            $attendance = $this->todayOf($child);
            $attendance->update([
                'checked_out_at' => now(),
                'checked_out_by' => $staff?->id,
                'picked_up_by' => $candidate->user?->id,
                'pickup_verified' => true,
                'pickup_method' => $candidate->method,
                'pickup_pass_id' => $candidate->pass?->id,
                'override_reason' => null,
            ]);

            $this->audit->record(AuditAction::PickupVerified, $child, $this->trail($candidate) + ['attendance_id' => $attendance->id], $staff);

            return $attendance;
        });
    }

    /**
     * A manager's decision to hand a child over without a verified collector
     * (e.g. an emergency). Never marked as verified; always reported.
     */
    public function overrideCheckOut(Child $child, User $manager, string $collectorName, string $reason): Attendance
    {
        return DB::transaction(function () use ($child, $manager, $collectorName, $reason) {
            $attendance = $this->todayOf($child);
            $attendance->update([
                'checked_out_at' => now(),
                'checked_out_by' => $manager->id,
                'picked_up_by' => null,
                'pickup_verified' => false,
                'pickup_method' => PickupMethod::ManualOverride,
                'pickup_pass_id' => null,
                'override_reason' => mb_substr("{$collectorName}: {$reason}", 0, 255),
            ]);

            $this->audit->record(AuditAction::PickupOverridden, $child, [
                'collector_name' => $collectorName,
                'reason' => $reason,
                'attendance_id' => $attendance->id,
            ], $manager);

            return $attendance;
        });
    }

    private function todayOf(Child $child): Attendance
    {
        return Attendance::firstOrCreate(
            ['child_id' => $child->id, 'date' => today()],
            ['tenant_id' => $child->tenant_id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function trail(PickupCandidate $candidate): array
    {
        return array_filter([
            'method' => $candidate->method->value,
            'collector_id' => $candidate->user?->id,
            'pickup_pass_id' => $candidate->pass?->id,
            'collector_name' => $candidate->name,
        ], fn ($v) => $v !== null);
    }
}
