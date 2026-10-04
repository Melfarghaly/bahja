<?php

namespace App\Services\Messaging;

/**
 * Sends one SMS. Implementations wrap a provider; callers never know which.
 * Phone numbers are Egyptian mobiles in local format (01xxxxxxxxx).
 */
interface SmsGateway
{
    public function send(string $phone, string $message): SmsResult;
}
