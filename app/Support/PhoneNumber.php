<?php

namespace App\Support;

/**
 * Egyptian mobile numbers in one canonical form: 01XXXXXXXXX (11 digits).
 * Accepts +20 / 0020 / 20 prefixes, spaces and dashes.
 */
final class PhoneNumber
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '20') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    public static function isValidMobile(string $phone): bool
    {
        return (bool) preg_match('/^01[0125]\d{8}$/', self::normalize($phone));
    }
}
