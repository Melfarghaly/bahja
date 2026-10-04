<?php

namespace App\Services\Notifications\Push;

final class PushResult
{
    /**
     * @param  bool  $invalidToken  the app was uninstalled or the token rotated: forget the device
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $providerReference = null,
        public readonly ?string $error = null,
        public readonly bool $invalidToken = false,
    ) {}
}
