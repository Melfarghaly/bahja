<?php

namespace App\Services\Notifications\Push;

/**
 * Sends one push notification to one device. Implementations wrap a
 * provider (FCM); callers never know which.
 */
interface PushGateway
{
    public function send(string $deviceToken, PushMessage $message): PushResult;
}
