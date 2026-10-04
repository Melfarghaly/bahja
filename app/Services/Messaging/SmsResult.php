<?php

namespace App\Services\Messaging;

final class SmsResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $providerReference = null,
        public readonly ?string $error = null,
    ) {}
}
