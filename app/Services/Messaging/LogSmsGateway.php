<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development / pre-launch driver: writes messages to the log instead of
 * sending them. Swap SMS_DRIVER once a provider contract is signed.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): SmsResult
    {
        Log::channel(config('services.sms.log_channel'))->info('SMS (not sent: log driver)', [
            'to' => $phone,
            'message' => $message,
        ]);

        return new SmsResult(true, 'log-'.Str::uuid());
    }
}
