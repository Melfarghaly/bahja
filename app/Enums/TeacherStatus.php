<?php

namespace App\Enums;

enum TeacherStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Inactive = 'inactive';
}
