<?php

namespace App\Enums;

/**
 * Lifecycle of an online payment attempt. Only Pending can move; every other
 * state is final, which is what makes duplicate webhooks harmless.
 */
enum PaymentIntentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case NeedsReview = 'needs_review'; // money arrived but couldn't be applied (e.g. invoice already settled)

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الدفع',
            self::Succeeded => 'تم الدفع',
            self::Failed => 'فشل',
            self::Expired => 'انتهت الصلاحية',
            self::NeedsReview => 'يحتاج مراجعة',
        };
    }
}
