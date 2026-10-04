<?php

namespace Tests\Support;

use App\Services\Messaging\SmsGateway;
use App\Services\Messaging\SmsResult;

/**
 * Captures SMS instead of sending them.
 */
class FakeSmsGateway implements SmsGateway
{
    /**
     * @var array<int, array{phone: string, message: string}>
     */
    public array $sent = [];

    public bool $fail = false;

    public function send(string $phone, string $message): SmsResult
    {
        if ($this->fail) {
            return new SmsResult(false, error: 'provider down');
        }

        $this->sent[] = ['phone' => $phone, 'message' => $message];

        return new SmsResult(true, 'fake-'.count($this->sent));
    }
}
