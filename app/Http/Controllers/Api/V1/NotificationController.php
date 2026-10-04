<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListNotificationsRequest;
use App\Http\Resources\UserNotificationResource;
use App\Services\Notifications\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The in-app inbox for the current nursery (every role).
 */
class NotificationController extends Controller
{
    public function __construct(private InboxService $inbox) {}

    public function index(ListNotificationsRequest $request): AnonymousResourceCollection
    {
        return UserNotificationResource::collection($this->inbox->list($request->user(), $request->boolean('unread')))
            ->additional(['unread_count' => $this->inbox->unreadCount($request->user())]);
    }

    public function read(Request $request, int $notification): UserNotificationResource
    {
        return new UserNotificationResource($this->inbox->markRead($request->user(), $notification));
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->inbox->markAllRead($request->user());

        return response()->json(['unread_count' => 0]);
    }
}
