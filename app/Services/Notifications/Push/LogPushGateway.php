<?php

namespace App\Services\Notifications\Push;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development driver: writes pushes to the log instead of sending them.
 */
class LogPushGateway implements PushGateway
{
    public function send(string $deviceToken, PushMessage $message): PushResult
    {
        Log::channel(config('services.push.log_channel'))->info('Push (not sent: log driver)', [
            'token' => Str::limit($deviceToken, 12),
            'title' => $message->title,
            'body' => $message->body,
            'data' => $message->data,
        ]);

        return new PushResult(true, 'log-'.Str::uuid());
    }
}
