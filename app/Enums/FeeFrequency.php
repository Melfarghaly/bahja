<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum FeeFrequency: string
{
    case Monthly = 'monthly';
    case Term = 'term';         // every 3 months from the start month
    case Annual = 'annual';     // every 12 months from the start month
    case OneOff = 'one_off';    // only in the start month (registration, uniform…)

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'شهري',
            self::Term => 'فصلي (كل 3 أشهر)',
            self::Annual => 'سنوي',
            self::OneOff => 'مرة واحدة',
        };
    }

    /**
     * Whether a plan that started in $startMonth is billed in $period's month.
     */
    public function isDueIn(CarbonInterface $startMonth, CarbonInterface $period): bool
    {
        $diff = ($period->year - $startMonth->year) * 12 + ($period->month - $startMonth->month);

        if ($diff < 0) {
            return false;
        }

        return match ($this) {
            self::Monthly => true,
            self::Term => $diff % 3 === 0,
            self::Annual => $diff % 12 === 0,
            self::OneOff => $diff === 0,
        };
    }
}
