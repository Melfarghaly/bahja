<?php

namespace App\Services\Notifications;

use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * The nursery's quiet hours (Cairo time, default 21:00–07:00): non-urgent
 * pushes wait until they end. The window may cross midnight.
 */
class QuietHours
{
    public const TIMEZONE = 'Africa/Cairo';

    public const DEFAULT_START = '21:00';

    public const DEFAULT_END = '07:00';

    /**
     * @return array{start: string, end: string}
     */
    public function window(Tenant $tenant): array
    {
        $settings = $tenant->settings ?? [];

        return [
            'start' => $this->time($settings['quiet_hours_start'] ?? null) ?? self::DEFAULT_START,
            'end' => $this->time($settings['quiet_hours_end'] ?? null) ?? self::DEFAULT_END,
        ];
    }

    /**
     * When the current quiet period ends (app timezone), or null outside quiet hours.
     */
    public function endsAt(Tenant $tenant, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        ['start' => $start, 'end' => $end] = $this->window($tenant);

        if ($start === $end) {
            return null;    // no quiet hours
        }

        $local = ($now ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE);
        $clock = $local->format('H:i');
        $endToday = CarbonImmutable::parse($local->toDateString().' '.$end, self::TIMEZONE);

        $quiet = $start < $end
            ? $clock >= $start && $clock < $end          // e.g. 13:00–15:00
            : $clock >= $start || $clock < $end;         // e.g. 21:00–07:00

        if (! $quiet) {
            return null;
        }

        // In the app timezone: Eloquent stores a datetime's clock time as-is.
        return ($clock < $end ? $endToday : $endToday->addDay())->setTimezone(config('app.timezone'));
    }

    private function time(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : null;
    }
}
