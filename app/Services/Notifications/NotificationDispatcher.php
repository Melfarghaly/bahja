<?php

namespace App\Services\Notifications;

use App\Enums\NotificationPriority;
use App\Enums\PushStatus;
use App\Models\NotificationDelivery;
use App\Models\PushDevice;
use App\Models\UserNotification;
use App\Services\Messaging\SmsGateway;
use App\Services\Notifications\Push\PushGateway;
use App\Services\Notifications\Push\PushMessage;
use Illuminate\Support\Facades\DB;

/**
 * Delivers one inbox notification: push to every device of the user, then
 * SMS when the priority calls for it and no device was reached. Runs once:
 * a notification that already left `pending` is not sent again.
 */
class NotificationDispatcher
{
    public function __construct(
        private PushGateway $push,
        private SmsGateway $sms,
    ) {}

    public function deliver(UserNotification $notification): PushStatus
    {
        $claimed = UserNotification::whereKey($notification->id)
            ->whereIn('push_status', [PushStatus::Pending->value, PushStatus::Deferred->value])
            ->update(['push_status' => PushStatus::Sent->value]);

        if ($claimed === 0) {
            return $notification->fresh()->push_status;    // already handled
        }

        $notification->loadMissing(['user.pushDevices', 'tenant']);
        $priority = $notification->type->priority();
        $preferences = $this->preferences($notification);

        $reached = false;
        $attempted = false;

        if ($preferences['push'] || $priority->overridesPreferences()) {
            foreach ($notification->user->pushDevices as $device) {
                $attempted = true;
                $reached = $this->pushTo($notification, $device, $priority) || $reached;
            }
        }

        $phone = $notification->user->phone;
        if ($priority->fallsBackToSms() && ! $reached && filled($phone)
            && ($preferences['sms'] || $priority->overridesPreferences())) {
            $attempted = true;
            $reached = $this->smsTo($notification, $phone);
        }

        $status = match (true) {
            $reached => PushStatus::Sent,
            $attempted => PushStatus::Failed,
            default => PushStatus::Skipped,
        };
        $notification->update(['push_status' => $status]);

        return $status;
    }

    private function pushTo(UserNotification $notification, PushDevice $device, NotificationPriority $priority): bool
    {
        $text = $notification->render($device->locale);
        $result = $this->push->send($device->token, new PushMessage(
            $text['title'],
            $text['body'],
            array_filter([
                'notification_id' => (string) $notification->id,
                'type' => $notification->type->value,
                'tenant_id' => (string) $notification->tenant_id,
                'child_id' => $notification->child_id ? (string) $notification->child_id : null,
                ...array_map('strval', $notification->data ?? []),
            ], fn ($value) => $value !== null),
            urgent: $priority->bypassesQuietHours(),
        ));

        $this->record($notification, NotificationDelivery::PUSH, $result->successful, $result->providerReference, $result->error, $result->invalidToken ? null : $device->id);

        if ($result->invalidToken) {
            $device->delete();
        }

        return $result->successful;
    }

    private function smsTo(UserNotification $notification, string $phone): bool
    {
        $text = $notification->render('ar');
        $result = $this->sms->send($phone, $text['body']);

        $this->record($notification, NotificationDelivery::SMS, $result->successful, $result->providerReference, $result->error);

        return $result->successful;
    }

    private function record(UserNotification $notification, string $channel, bool $ok, ?string $reference, ?string $error, ?int $deviceId = null): void
    {
        NotificationDelivery::create([
            'tenant_id' => $notification->tenant_id,
            'user_notification_id' => $notification->id,
            'push_device_id' => $deviceId,
            'channel' => $channel,
            'status' => $ok ? 'sent' : 'failed',
            'provider_ref' => $reference,
            'error' => $error === null ? null : mb_substr($error, 0, 500),
        ]);
    }

    /**
     * The guardian's choices for this child (both on by default). Without a
     * child, or for staff, everything is on.
     *
     * @return array{push: bool, sms: bool}
     */
    private function preferences(UserNotification $notification): array
    {
        $raw = $notification->child_id === null ? null : DB::table('child_guardian')
            ->where('child_id', $notification->child_id)
            ->where('guardian_id', $notification->user_id)
            ->value('notify_preferences');

        $preferences = json_decode((string) $raw, true) ?: [];

        return [
            'push' => ($preferences['push'] ?? true) !== false,
            'sms' => ($preferences['sms'] ?? true) !== false,
        ];
    }
}
