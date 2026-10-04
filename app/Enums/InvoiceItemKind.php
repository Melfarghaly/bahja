<?php

namespace App\Enums;

enum InvoiceItemKind: string
{
    case Fee = 'fee';            // positive amount
    case Discount = 'discount';  // negative amount
}
