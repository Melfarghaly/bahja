<?php

namespace App\Enums;

enum GuardianRole: string
{
    case Primary = 'primary';
    case Viewer = 'viewer';
    case PickupAuthorized = 'pickup_authorized';
    case EmergencyContact = 'emergency_contact';
}
