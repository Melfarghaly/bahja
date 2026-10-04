<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttendanceSheetRequest;
use App\Http\Requests\Api\BulkCheckInRequest;
use App\Http\Requests\CheckInRequest;
use App\Http\Requests\CheckOutRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\AttendanceSheetRowResource;
use App\Models\Child;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\AttendanceSheetService;
use App\Services\Pickup\PickupVerificationService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private PickupVerificationService $verification,
        private TenantContext $tenantContext,
    ) {}

    /**
     * The daily sheet: every active child with today's (or a past day's) state.
     */
    public function index(AttendanceSheetRequest $request, AttendanceSheetService $sheets): JsonResponse
    {
        $date = CarbonImmutable::parse($request->validated('date') ?? today()->toDateString());
        $sheet = $sheets->forDate($date, $request->validated('classroom_id'));

        return AttendanceSheetRowResource::collection($sheet['rows'])
            ->additional(['date' => $date->toDateString(), 'summary' => $sheet['summary']])
            ->response();
    }

    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $child = Child::findOrFail($request->validated('child_id'));
        $method = AttendanceMethod::tryFrom($request->validated('method') ?? '') ?? AttendanceMethod::Qr;

        $record = $this->attendance->checkIn($child, $request->user(), $method);

        // Recording today's attendance is an update of the day's sheet: always 200.
        return (new AttendanceResource($record))->response()->setStatusCode(200);
    }

    /**
     * Records several check-ins at once (morning arrival, or a device that
     * was offline syncing what it recorded).
     */
    public function bulkCheckIn(BulkCheckInRequest $request): AnonymousResourceCollection
    {
        $method = AttendanceMethod::tryFrom($request->validated('method') ?? '') ?? AttendanceMethod::Qr;
        $items = collect($request->validated('children'));
        $children = Child::whereKey($items->pluck('child_id'))->get()->keyBy('id');

        $records = $items->map(fn (array $item) => $this->attendance->checkIn(
            $children[$item['child_id']],
            $request->user(),
            $method,
            isset($item['checked_in_at']) ? CarbonImmutable::parse($item['checked_in_at']) : null,
        ));

        return AttendanceResource::collection($records);
    }

    public function checkOut(CheckOutRequest $request): JsonResponse
    {
        $child = Child::findOrFail($request->validated('child_id'));

        if ($request->isOverride()) {
            abort_unless($request->user()->can('update', $child), 403, __('pickup.override_requires_manager'));

            $record = $this->attendance->overrideCheckOut(
                $child, $request->user(), $request->validated('collector_name'), $request->validated('override_reason'),
            );
        } else {
            $collector = filled($request->validated('collector_id'))
                ? User::findOrFail($request->validated('collector_id'))
                : $this->verification->identify($this->tenantContext->get(), $request->validated());

            $record = $this->attendance->checkOut($child, $collector, $request->user());
        }

        // Check-out updates the day's record; it is never a "created" response
        // even when no check-in preceded it.
        return (new AttendanceResource($record))->response()->setStatusCode(200);
    }
}
