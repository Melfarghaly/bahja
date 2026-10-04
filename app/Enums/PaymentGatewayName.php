<?php

namespace App\Enums;

enum PaymentGatewayName: string
{
    case Paymob = 'paymob';   // cards and mobile wallets (hosted checkout)
    case Fawry = 'fawry';     // pay at any Fawry outlet with a reference number

    public function label(): string
    {
        return match ($this) {
            self::Paymob => 'بطاقة أو محفظة إلكترونية',
            self::Fawry => 'فوري (كود دفع)',
        };
    }

    /**
     * How a successful payment through this gateway is recorded.
     */
    public function paymentMethod(): TuitionPaymentMethod
    {
        return match ($this) {
            self::Paymob => TuitionPaymentMethod::Card,
            self::Fawry => TuitionPaymentMethod::Fawry,
        };
    }
}
