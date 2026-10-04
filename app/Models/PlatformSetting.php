<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide branding controlled by the super admin. Treated as a singleton:
 * there is always exactly one row, fetched via current().
 */
class PlatformSetting extends Model
{
    protected $fillable = [
        'brand_name',
        'logo_path',
        'primary_color',
        'secondary_color',
        'accent_color',
        'support_email',
        'support_phone',
    ];

    /**
     * Fetch (or lazily create) the single settings row.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
