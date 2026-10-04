<?php

namespace App\Enums;

enum CustodyFlag: string
{
    case None = 'none';
    case ViewOnly = 'view_only';
    case Blocked = 'blocked';
}
