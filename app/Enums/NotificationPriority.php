<?php

namespace App\Enums;

/**
 * How hard the router tries to reach someone:
 * - emergency: push + SMS always, ignores preferences and quiet hours;
 * - high: push now (even in quiet hours), SMS if no device was reached
 *   and the guardian has not turned SMS off;
 * - normal: push, held until quiet hours end;
 * - low: inbox, plus a push outside quiet hours only.
 */
enum NotificationPriority: string
{
    case Emergency = 'emergency';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function bypassesQuietHours(): bool
    {
        return in_array($this, [self::Emergency, self::High], true);
    }

    public function overridesPreferences(): bool
    {
        return $this === self::Emergency;
    }

    public function fallsBackToSms(): bool
    {
        return in_array($this, [self::Emergency, self::High], true);
    }
}
