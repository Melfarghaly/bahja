<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttendanceSheetRequest;
use App\Http\Requests\CheckInRequest;
use App\Http\Requests\CheckOutRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\AttendanceSheetRowResource;
use App\Models\Child;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\AttendanceSheetService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

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

    public function checkOut(CheckOutRequest $request): JsonResponse
    {
        $child = Child::findOrFail($request->validated('child_id'));
        $collector = User::findOrFail($request->validated('collector_id'));

        $record = $this->attendance->checkOut($child, $collector);

        // Check-out updates the day's record; it is never a "created" response
        // even when no check-in preceded it.
        return (new AttendanceResource($record))->response()->setStatusCode(200);
    }
}
