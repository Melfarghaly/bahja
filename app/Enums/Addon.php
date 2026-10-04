<?php

namespace App\Enums;

/**
 * Paid extras that can be bought on top of any paid plan.
 */
enum Addon: string
{
    case Buses = 'buses';
    case ExtraStaff = 'extra_staff';
    case ExtraChildren = 'extra_children';

    public function label(): string
    {
        return match ($this) {
            self::Buses => 'وحدة الحافلات',
            self::ExtraStaff => 'موظف إضافي',
            self::ExtraChildren => 'شريحة 10 أطفال إضافيين',
        };
    }

    /**
     * Features this add-on unlocks.
     *
     * @return array<int, Feature>
     */
    public function grants(): array
    {
        return match ($this) {
            self::Buses => [Feature::Buses],
            default => [],
        };
    }

    /**
     * How much one unit of this add-on raises a limit.
     *
     * @return array<string, int> keyed by Limit value
     */
    public function extends(): array
    {
        return match ($this) {
            self::ExtraStaff => [Limit::Staff->value => 1],
            self::ExtraChildren => [Limit::Children->value => 10],
            default => [],
        };
    }
}
