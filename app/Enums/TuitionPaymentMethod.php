<?php

namespace App\Enums;

enum TuitionPaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case InstaPay = 'instapay';
    case Wallet = 'wallet';
    case Card = 'card';
    case Fawry = 'fawry';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدي',
            self::BankTransfer => 'تحويل بنكي',
            self::InstaPay => 'InstaPay',
            self::Wallet => 'محفظة إلكترونية',
            self::Card => 'بطاقة',
            self::Fawry => 'فوري',
        };
    }

    /**
     * The ledger account the money lands in.
     */
    public function ledgerAccount(): LedgerAccount
    {
        return $this === self::Cash ? LedgerAccount::Cash : LedgerAccount::Bank;
    }

    /**
     * Methods a nursery records by hand (online methods arrive via gateways).
     *
     * @return array<int, self>
     */
    public static function manual(): array
    {
        return [self::Cash, self::BankTransfer, self::InstaPay, self::Wallet];
    }
}
