<?php

namespace Tests\Support;

use App\Services\Notifications\Push\PushGateway;
use App\Services\Notifications\Push\PushMessage;
use App\Services\Notifications\Push\PushResult;

/**
 * Captures pushes instead of sending them. Tokens listed in $unregistered
 * answer like an uninstalled app.
 */
class FakePushGateway implements PushGateway
{
    /**
     * @var array<int, array{token: string, message: PushMessage}>
     */
    public array $sent = [];

    /**
     * @var array<int, string>
     */
    public array $unregistered = [];

    public function send(string $deviceToken, PushMessage $message): PushResult
    {
        if (in_array($deviceToken, $this->unregistered, true)) {
            return new PushResult(false, error: 'UNREGISTERED: Requested entity was not found.', invalidToken: true);
        }

        $this->sent[] = ['token' => $deviceToken, 'message' => $message];

        return new PushResult(true, 'projects/bahga/messages/'.count($this->sent));
    }
}
