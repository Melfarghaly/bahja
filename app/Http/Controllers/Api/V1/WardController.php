<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateWardNotificationsRequest;
use App\Http\Requests\Api\WardAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\WardResource;
use App\Models\Child;
use App\Services\WardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Guardian app: "my children" in the current nursery. Every endpoint is
 * limited to the signed-in guardian's own (non-blocked) links.
 */
class WardController extends Controller
{
    public function __construct(private WardService $wards) {}

    /**
     * The unified siblings view, with today's attendance for each child.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return WardResource::collection($this->wards->list($request->user()));
    }

    public function show(Request $request, Child $child): WardResource
    {
        return new WardResource($this->wards->find($request->user(), $child));
    }

    public function attendance(WardAttendanceRequest $request, Child $child): AnonymousResourceCollection
    {
        $from = $request->validated('from') ? CarbonImmutable::parse($request->validated('from')) : null;
        $to = $request->validated('to') ? CarbonImmutable::parse($request->validated('to')) : null;

        return AttendanceResource::collection($this->wards->attendance($request->user(), $child, $from, $to));
    }

    public function updateNotifications(UpdateWardNotificationsRequest $request, Child $child): WardResource
    {
        $preferences = collect($request->validated())->map(fn ($value) => (bool) $value)->all();

        return new WardResource($this->wards->updateNotifications($request->user(), $child, $preferences));
    }
}
