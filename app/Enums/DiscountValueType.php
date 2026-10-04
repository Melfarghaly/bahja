<?php

namespace App\Enums;

enum DiscountValueType: string
{
    case Percent = 'percent';   // value in basis points (10000 = 100%)
    case Fixed = 'fixed';       // value in piasters
}
