<?php

namespace App\Enums;

/**
 * Kinds of Daily Wall updates, each posted in one or two taps.
 */
enum MomentType: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Note = 'note';
    case Meal = 'meal';
    case Nap = 'nap';
    case Diaper = 'diaper';
    case Mood = 'mood';
    case Activity = 'activity';
    case Health = 'health';
    case Incident = 'incident';     // the family must acknowledge it

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'صور',
            self::Video => 'فيديو',
            self::Note => 'ملاحظة',
            self::Meal => 'وجبة',
            self::Nap => 'نوم',
            self::Diaper => 'تغيير حفاض',
            self::Mood => 'المزاج',
            self::Activity => 'نشاط',
            self::Health => 'ملاحظة صحية',
            self::Incident => 'حادثة بسيطة',
        };
    }

    public function requiresAcknowledgement(): bool
    {
        return $this === self::Incident;
    }
}
