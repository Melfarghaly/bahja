<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Http\Requests\CheckOutRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Child;
use App\Models\User;
use App\Services\AttendanceService;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    public function checkIn(CheckInRequest $request): AttendanceResource
    {
        $child = Child::findOrFail($request->validated('child_id'));
        $method = AttendanceMethod::tryFrom($request->validated('method') ?? '') ?? AttendanceMethod::Qr;

        $record = $this->attendance->checkIn($child, $request->user(), $method);

        return new AttendanceResource($record);
    }

    public function checkOut(CheckOutRequest $request): AttendanceResource
    {
        $child = Child::findOrFail($request->validated('child_id'));
        $collector = User::findOrFail($request->validated('collector_id'));

        $record = $this->attendance->checkOut($child, $collector);

        return new AttendanceResource($record);
    }
}
