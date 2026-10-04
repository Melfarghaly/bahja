<?php

namespace App\Enums;

/**
 * Release toggles for V2 modules (Laravel Pennant, scoped per nursery).
 * Unlike App\Enums\Feature — what a plan *sells* — a rollout flag controls
 * whether a module is *switched on yet* for a nursery during gradual release.
 */
enum RolloutFlag: string
{
    case BahgaPay = 'bahga-pay';
    case SafePickupV2 = 'safe-pickup-v2';
    case DailyWall = 'daily-wall';
    case MessagingHub = 'messaging-hub';
    case LostChild = 'lost-child';

    public function label(): string
    {
        return match ($this) {
            self::BahgaPay => 'بهجة باي (التحصيل)',
            self::SafePickupV2 => 'الاستلام الآمن 2.0',
            self::DailyWall => 'الحائط واليوميات',
            self::MessagingHub => 'مركز الرسائل',
            self::LostChild => 'نظام الطفل التائه',
        };
    }
}
