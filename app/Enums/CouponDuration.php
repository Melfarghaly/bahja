<?php

namespace App\Enums;

enum CouponDuration: string
{
    case Once = 'once';            // first billing month only
    case Repeating = 'repeating';  // for duration_months
    case Forever = 'forever';      // lifetime (e.g. the founders program)

    public function label(): string
    {
        return match ($this) {
            self::Once => 'الشهر الأول فقط',
            self::Repeating => 'لعدة أشهر',
            self::Forever => 'مدى الحياة',
        };
    }
}
