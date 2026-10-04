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
    case FeePlanSaved = 'fee_plan.saved';
    case FeeDiscountSaved = 'fee_discount.saved';
    case FeeAssigned = 'fee.assigned';
    case FeeAssignmentEnded = 'fee.assignment_ended';
    case TuitionInvoicesGenerated = 'tuition.invoices_generated';
    case TuitionInvoiceVoided = 'tuition.invoice_voided';
    case TuitionPaymentRecorded = 'tuition.payment_recorded';
    case TuitionPaymentVoided = 'tuition.payment_voided';
    case OnlinePaymentNeedsReview = 'tuition.online_payment_needs_review';
    case TuitionInvoiceEscalated = 'tuition.invoice_escalated';
}
