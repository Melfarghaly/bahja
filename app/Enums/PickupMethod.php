<?php

namespace App\Enums;

/**
 * How the person collecting a child was verified at check-out.
 */
enum PickupMethod: string
{
    case DynamicQr = 'dynamic_qr';            // guardian's rotating QR from the app
    case PassCode = 'pass_code';              // one-time pass issued by a guardian
    case Guardian = 'guardian';               // staff picked the guardian from the authorized list
    case ManualOverride = 'manual_override';  // manager decision, reason mandatory

    public function label(): string
    {
        return match ($this) {
            self::DynamicQr => 'QR وليّ الأمر',
            self::PassCode => 'تصريح استلام',
            self::Guardian => 'اختيار من قائمة المخوَّلين',
            self::ManualOverride => 'تجاوز يدوي من الإدارة',
        };
    }
}
