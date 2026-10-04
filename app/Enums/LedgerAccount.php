<?php

namespace App\Enums;

/**
 * Chart of accounts for a nursery's tuition ledger (double entry).
 */
enum LedgerAccount: string
{
    case Receivable = 'receivable';          // asset: what families owe
    case Cash = 'cash';                      // asset: cash in the nursery
    case Bank = 'bank';                      // asset: transfers / wallets / gateways
    case TuitionRevenue = 'tuition_revenue'; // income: gross fees
    case Discounts = 'discounts';            // contra-income: discounts granted

    public function label(): string
    {
        return match ($this) {
            self::Receivable => 'ذمم أولياء الأمور',
            self::Cash => 'الخزينة',
            self::Bank => 'البنك والمحافظ',
            self::TuitionRevenue => 'إيراد المصروفات',
            self::Discounts => 'الخصومات الممنوحة',
        };
    }
}
