<?php

namespace App\Enums;

enum DiscountType: string
{
    case Sibling = 'sibling';           // applied automatically from the 2nd child of a payer
    case Staff = 'staff';
    case Scholarship = 'scholarship';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Sibling => 'خصم الإخوة (تلقائي)',
            self::Staff => 'خصم موظف',
            self::Scholarship => 'منحة',
            self::Custom => 'خصم خاص',
        };
    }
}
