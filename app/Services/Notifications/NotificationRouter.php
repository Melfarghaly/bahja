<?php

namespace App\Services\Notifications;

use App\Enums\CustodyFlag;
use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\PushStatus;
use App\Jobs\DeliverNotification;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

/**
 * The one door every notification goes through. It always files the
 * notification in the user's in-app inbox, then decides when and how to
 * deliver it (see NotificationPriority), and hands delivery to a queued job.
 *
 * A guardian who is custody-blocked from a child is never told anything
 * about that child, whatever the priority.
 */
class NotificationRouter
{
    public function __construct(private QuietHours $quietHours) {}

    /**
     * @param  array<string, string|int>  $params  placeholders for the text in lang/*\/notifications.php
     * @param  array<string, string|int>  $data  deep link for the app
     */
    public function notify(
        Tenant $tenant,
        User $user,
        NotificationType $type,
        array $params = [],
        ?Child $child = null,
        array $data = [],
    ): ?UserNotification {
        if ($child !== null && $this->blockedFrom($user, $child)) {
            return null;
        }

        $priority = $type->priority();
        $quietUntil = $priority->bypassesQuietHours() ? null : $this->quietHours->endsAt($tenant);

        $notification = UserNotification::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'child_id' => $child?->id,
            'type' => $type,
            'params' => $params,
            'data' => $data,
            'push_status' => match (true) {
                $quietUntil === null => PushStatus::Pending,
                $priority === NotificationPriority::Low => PushStatus::Skipped,   // inbox only
                default => PushStatus::Deferred,
            },
            'deliver_after' => $priority === NotificationPriority::Low ? null : $quietUntil,
        ]);

        if ($notification->push_status === PushStatus::Pending) {
            DeliverNotification::dispatch($notification->id, $tenant->id)->afterCommit();
        }

        return $notification;
    }

    private function blockedFrom(User $user, Child $child): bool
    {
        return DB::table('child_guardian')
            ->where('child_id', $child->id)
            ->where('guardian_id', $user->id)
            ->where('custody_flag', CustodyFlag::Blocked->value)
            ->exists();
    }
}
