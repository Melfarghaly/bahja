<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One push or SMS attempt for a notification.
 */
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    public const PUSH = 'push';

    public const SMS = 'sms';

    protected $fillable = ['tenant_id', 'user_notification_id', 'push_device_id', 'channel', 'status', 'provider_ref', 'error'];
}
