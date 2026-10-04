<?php

namespace App\Enums;

/**
 * Countable quotas. A null limit means unlimited.
 */
enum Limit: string
{
    case Children = 'children';
    case Staff = 'staff';
    case DailyPhotosPerChild = 'daily_photos_per_child';
    case MediaRetentionDays = 'media_retention_days';

    public function label(): string
    {
        return match ($this) {
            self::Children => 'الأطفال',
            self::Staff => 'الموظفون',
            self::DailyPhotosPerChild => 'صور يومية لكل طفل',
            self::MediaRetentionDays => 'مدة الاحتفاظ بالوسائط (يوم)',
        };
    }
}
