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
    case MomentPosted = 'moment_posted';
    case IncidentReported = 'incident_reported';

    public function priority(): NotificationPriority
    {
        return match ($this) {
            self::LatePickup, self::LatePickupManagers, self::IncidentReported => NotificationPriority::High,
            self::ChildArrived, self::ChildPickedUp, self::MomentPosted => NotificationPriority::Normal,
        };
    }
}
