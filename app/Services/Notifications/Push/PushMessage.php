<?php

namespace App\Services\Notifications\Push;

final class PushMessage
{
    /**
     * @param  array<string, string>  $data  deep-link payload for the app (strings only)
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly bool $urgent = false,
    ) {}
}
