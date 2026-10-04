<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Database\Factories\PushDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An app install that can receive push notifications (an FCM token).
 */
class PushDevice extends Model
{
    /** @use HasFactory<PushDeviceFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'personal_access_token_id', 'platform', 'token', 'locale', 'app_version', 'last_seen_at'];

    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['platform' => DevicePlatform::class, 'last_seen_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
