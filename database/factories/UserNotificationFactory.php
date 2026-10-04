<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Enums\PushStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'type' => NotificationType::ChildArrived,
            'params' => ['child' => 'يوسف', 'nursery' => 'حضانة البراعم', 'time' => '08:05'],
            'push_status' => PushStatus::Sent,
        ];
    }
}
