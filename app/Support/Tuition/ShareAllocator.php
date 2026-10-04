<?php

namespace App\Support\Tuition;

/**
 * Splits an amount between parties by basis-point shares so the parts always
 * add up to exactly the original amount (largest-remainder method; ties go
 * to the lowest party key, which keeps the result deterministic).
 */
final class ShareAllocator
{
    /**
     * @param  array<int, int>  $shares  party key => basis points (summing to 10000)
     * @return array<int, int> party key => piasters
     */
    public static function allocate(int $amount, array $shares): array
    {
        ksort($shares);
        $total = array_sum($shares);

        if ($total <= 0) {
            return array_map(fn () => 0, $shares);
        }

        $parts = [];
        $remainders = [];

        foreach ($shares as $key => $share) {
            $exact = $amount * $share;
            $parts[$key] = intdiv($exact, $total);
            $remainders[$key] = $exact % $total;
        }

        $left = $amount - array_sum($parts);
        arsort($remainders, SORT_NUMERIC);   // stable: equal remainders keep key order

        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            $parts[$key]++;
            $left--;
        }

        return $parts;
    }

    /**
     * Equal shares for the given parties (remainder basis points to the first).
     *
     * @param  array<int, int>  $keys
     * @return array<int, int>
     */
    public static function equal(array $keys): array
    {
        sort($keys);
        $count = count($keys);
        $shares = array_fill_keys($keys, intdiv(10000, $count));
        $shares[$keys[0]] += 10000 - array_sum($shares);

        return $shares;
    }
}
