<?php

namespace App\Enums;

enum AuditAction: string
{
    case PickupVerified = 'pickup.verified';
    case PickupDenied = 'pickup.denied';
    case GuardianAttached = 'guardian.attached';
    case GuardianUpdated = 'guardian.updated';
    case GuardianDetached = 'guardian.detached';
    case EntitlementOverrideGranted = 'entitlement.override_granted';
    case EntitlementOverrideRevoked = 'entitlement.override_revoked';
    case AddonAdded = 'addon.added';
    case AddonRemoved = 'addon.removed';
    case RolloutChanged = 'rollout.changed';
    case CouponCreated = 'coupon.created';
    case CouponStatusChanged = 'coupon.status_changed';
    case CouponRedeemed = 'coupon.redeemed';
}
