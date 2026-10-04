<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Arr;

/**
 * Updates a nursery's profile and its preferences stored in `settings`
 * (merged key by key, so unrelated preferences are never wiped).
 */
class NurserySettingsService
{
    /**
     * Keys of the request that live inside the `settings` JSON column.
     */
    private const SETTINGS_KEYS = ['tuition_due_day', 'pickup_deadline', 'quiet_hours_start', 'quiet_hours_end'];

    /**
     * Keys stored as integers (the others are kept as strings).
     */
    private const INTEGER_KEYS = ['tuition_due_day'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $settings = $tenant->settings ?? [];

        foreach (self::SETTINGS_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] === null || $data[$key] === '') {
                    Arr::forget($settings, $key);
                } else {
                    $settings[$key] = in_array($key, self::INTEGER_KEYS, true) ? (int) $data[$key] : (string) $data[$key];
                }
            }
        }

        $tenant->update([...Arr::except($data, self::SETTINGS_KEYS), 'settings' => $settings ?: null]);

        return $tenant;
    }
}
