<?php

namespace App\Services;

use App\Enums\AttendanceMethod;
use App\Models\Attendance;
use App\Models\Child;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Records check-in and check-out, and verifies that the person collecting a
 * child is authorized to do so (pickup verification — the core safety feature).
 */
class AttendanceService
{
    public function checkIn(Child $child, User $by, AttendanceMethod $method = AttendanceMethod::Qr): Attendance
    {
        return Attendance::updateOrCreate(
            ['child_id' => $child->id, 'date' => today()],
            [
                'tenant_id' => $child->tenant_id,
                'checked_in_at' => now(),
                'checked_in_by' => $by->id,
                'check_in_method' => $method,
            ],
        );
    }

    /**
     * Check a child out, verifying the collector is on the authorized list.
     *
     * @throws AuthorizationException when the collector is not authorized to pick up this child.
     */
    public function checkOut(Child $child, User $collector): Attendance
    {
        $authorized = Gate::forUser($collector)->allows('pickup', $child);

        if (! $authorized) {
            throw new AuthorizationException('This person is not authorized to pick up this child.');
        }

        $attendance = Attendance::firstOrCreate(
            ['child_id' => $child->id, 'date' => today()],
            ['tenant_id' => $child->tenant_id],
        );

        $attendance->update([
            'checked_out_at' => now(),
            'picked_up_by' => $collector->id,
            'pickup_verified' => true,
        ]);

        return $attendance;
    }
}
