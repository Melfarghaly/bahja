<?php

namespace App\Enums;

/**
 * The reminder ladder for an unpaid tuition invoice, by days past due.
 */
enum DunningStep: string
{
    case BeforeDue = 'before_due';     // 3 days before
    case DueDay = 'due_day';
    case Overdue3 = 'overdue_3';
    case Overdue7 = 'overdue_7';
    case Escalated = 'escalated';      // 14 days late: handed to the nursery, no more SMS

    public function offsetDays(): int
    {
        return match ($this) {
            self::BeforeDue => -3,
            self::DueDay => 0,
            self::Overdue3 => 3,
            self::Overdue7 => 7,
            self::Escalated => 14,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::BeforeDue => 'تذكير قبل الاستحقاق',
            self::DueDay => 'تذكير يوم الاستحقاق',
            self::Overdue3 => 'تأخر 3 أيام',
            self::Overdue7 => 'تأخر 7 أيام',
            self::Escalated => 'تصعيد للإدارة',
        };
    }

    /**
     * The step that applies today, or null before the ladder starts. Only the
     * latest applicable step is used, so a late first run never sends a backlog.
     */
    public static function forDaysPastDue(int $days): ?self
    {
        $applicable = array_filter(self::cases(), fn (self $step) => $days >= $step->offsetDays());

        return $applicable === [] ? null : end($applicable);
    }
}
