<?php

namespace App\Enums;

enum AuditAction: string
{
    case PickupVerified = 'pickup.verified';
    case PickupDenied = 'pickup.denied';
    case GuardianAttached = 'guardian.attached';
    case GuardianUpdated = 'guardian.updated';
    case GuardianDetached = 'guardian.detached';
}
