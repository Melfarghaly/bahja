<?php

namespace App\Models;

use App\Enums\NotificationType;
use App\Enums\PushStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One notification in a user's in-app inbox (per nursery).
 */
class UserNotification extends Model
{
    /** @use HasFactory<UserNotificationFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'user_id', 'child_id', 'type', 'params', 'data', 'push_status', 'deliver_after', 'read_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'push_status' => PushStatus::class,
            'params' => 'array',
            'data' => 'array',
            'deliver_after' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Child, $this>
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /**
     * @param  Builder<UserNotification>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * The text in a language (the reader's, or the device's for a push).
     *
     * @return array{title: string, body: string}
     */
    public function render(?string $locale = null): array
    {
        $key = 'notifications.'.$this->type->value;
        $params = $this->params ?? [];

        return [
            'title' => __($key.'.title', $params, $locale),
            'body' => __($key.'.body', $params, $locale),
        ];
    }
}
