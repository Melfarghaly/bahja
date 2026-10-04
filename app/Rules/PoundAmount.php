<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A positive EGP amount typed by a person: up to 2 decimals, within a sane cap.
 */
class PoundAmount implements ValidationRule
{
    public function __construct(private int $maxPounds = 10_000_000) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = str_replace([',', ' '], '', trim((string) $value));

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $normalized)) {
            $fail('يجب أن يكون المبلغ رقماً موجباً بحد أقصى منزلتين عشريتين.');

            return;
        }

        $money = Money::fromPounds($normalized);

        if (! $money->isPositive() || $money->greaterThan(Money::of($this->maxPounds * 100))) {
            $fail('المبلغ خارج النطاق المسموح.');
        }
    }
}
