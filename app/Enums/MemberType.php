<?php

namespace App\Enums;

enum MemberType: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Guardian = 'guardian';
}
