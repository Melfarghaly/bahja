<?php

namespace App\Services;

use App\Enums\ChildStatus;
use App\Models\Child;
use App\Services\Pickup\LatePickupService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The teacher's daily sheet: every active child with their attendance state
 * for one day (absent → present → picked up).
 */
class AttendanceSheetService
{
    public const ABSENT = 'absent';

    public const PRESENT = 'present';

    public const PICKED_UP = 'picked_up';

    public function __construct(
        private TenantContext $tenantContext,
        private LatePickupService $late,
    ) {}

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, pickup_deadline: ?string, summary: array<string, int>}
     */
    public function forDate(CarbonImmutable $date, ?int $classroomId = null): array
    {
        $deadline = $this->late->deadline($this->tenantContext->get(), $date->setTime(12, 0));
        $pastDeadline = $deadline !== null && $date->isToday() && now()->gt($deadline);

        $children = Child::query()
            ->where('status', ChildStatus::Active->value)
            ->when($classroomId, fn ($q, $id) => $q->where('classroom_id', $id))
            ->with([
                'classroom:id,name',
                'attendances' => fn ($q) => $q->whereDate('date', $date->toDateString())->with('pickedUpBy:id,name'),
            ])
            ->orderBy('first_name')
            ->get();

        $rows = $children->map(function (Child $child) use ($pastDeadline) {
            $attendance = $child->attendances->first();
            $status = match (true) {
                $attendance?->checked_out_at !== null => self::PICKED_UP,
                $attendance?->checked_in_at !== null => self::PRESENT,
                default => self::ABSENT,
            };

            return [
                'child' => $child,
                'attendance' => $attendance,
                'status' => $status,
                'late_pickup' => $pastDeadline && $status === self::PRESENT,
            ];
        });

        return [
            'rows' => $rows,
            'pickup_deadline' => $deadline?->format('H:i'),
            'summary' => [
                'total' => $rows->count(),
                self::PRESENT => $rows->where('status', self::PRESENT)->count(),
                self::PICKED_UP => $rows->where('status', self::PICKED_UP)->count(),
                self::ABSENT => $rows->where('status', self::ABSENT)->count(),
                'late_pickup' => $rows->where('late_pickup', true)->count(),
            ],
        ];
    }
}
