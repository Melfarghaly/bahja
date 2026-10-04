<?php

namespace App\Enums;

/**
 * Every notification Bahga sends. Text lives in lang/{ar,en}/notifications.php
 * under the same key; the priority decides channels and quiet hours.
 */
enum NotificationType: string
{
    case ChildArrived = 'child_arrived';
    case ChildPickedUp = 'child_picked_up';
    case LatePickup = 'late_pickup';
    case LatePickupManagers = 'late_pickup_managers';

    public function priority(): NotificationPriority
    {
        return match ($this) {
            self::LatePickup, self::LatePickupManagers => NotificationPriority::High,
            self::ChildArrived, self::ChildPickedUp => NotificationPriority::Normal,
        };
    }
}
