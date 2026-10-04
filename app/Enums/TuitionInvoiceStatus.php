<?php

namespace App\Enums;

enum TuitionInvoiceStatus: string
{
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مستحقة',
            self::PartiallyPaid => 'مدفوعة جزئياً',
            self::Paid => 'مدفوعة',
            self::Void => 'ملغاة',
        };
    }

    public function isCollectible(): bool
    {
        return in_array($this, [self::Open, self::PartiallyPaid], true);
    }
}
