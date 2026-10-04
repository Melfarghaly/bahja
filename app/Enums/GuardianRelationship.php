<?php

namespace App\Enums;

enum GuardianRelationship: string
{
    case Mother = 'mother';
    case Father = 'father';
    case Grandparent = 'grandparent';
    case Nanny = 'nanny';
    case Driver = 'driver';
    case Other = 'other';
}
