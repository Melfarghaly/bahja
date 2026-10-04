<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use App\Models\Tenant;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_notification_id' => UserNotification::factory(),
            'channel' => NotificationDelivery::PUSH,
            'status' => 'sent',
        ];
    }
}
