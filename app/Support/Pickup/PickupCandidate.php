<?php

namespace App\Support\Pickup;

use App\Enums\PickupMethod;
use App\Models\PickupPass;
use App\Models\User;

/**
 * The person standing at the door, as identified by a QR, a pass code or the
 * staff's choice from the authorized list.
 */
final class PickupCandidate
{
    public function __construct(
        public readonly PickupMethod $method,
        public readonly string $name,
        public readonly ?string $phone,
        public readonly ?User $user = null,
        public readonly ?PickupPass $pass = null,
    ) {}
}
