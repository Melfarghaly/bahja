<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A user's in-app notifications in the current nursery (TenantScope).
 */
class InboxService
{
    /**
     * @return LengthAwarePaginator<int, UserNotification>
     */
    public function list(User $user, bool $unreadOnly = false): LengthAwarePaginator
    {
        return UserNotification::where('user_id', $user->id)
            ->when($unreadOnly, fn ($q) => $q->unread())
            ->latest('id')
            ->paginate(20);
    }

    public function unreadCount(User $user): int
    {
        return UserNotification::where('user_id', $user->id)->unread()->count();
    }

    /**
     * Someone else's notification is "not found", never "forbidden".
     */
    public function markRead(User $user, int $id): UserNotification
    {
        $notification = UserNotification::where('user_id', $user->id)->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }

    public function markAllRead(User $user): int
    {
        return UserNotification::where('user_id', $user->id)->unread()->update(['read_at' => now()]);
    }
}
