<?php

use App\Support\Tuition\ShareAllocator;

it('splits exactly, never losing or inventing a piaster', function (int $amount, array $shares) {
    $parts = ShareAllocator::allocate($amount, $shares);

    expect(array_sum($parts))->toBe($amount)
        ->and(array_keys($parts))->toBe(array_keys($shares));
})->with([
    [185_050, [7 => 6_000, 9 => 4_000]],
    [100_001, [1 => 3_333, 2 => 3_333, 3 => 3_334]],
    [1, [1 => 5_000, 2 => 5_000]],
    [0, [1 => 10_000]],
    [999_999, [4 => 1, 5 => 9_999]],
]);

it('gives 60/40 of 1500 as 900/600', function () {
    expect(ShareAllocator::allocate(150_000, [1 => 6_000, 2 => 4_000]))->toBe([1 => 90_000, 2 => 60_000]);
});

it('makes equal shares that sum to 100%', function () {
    expect(ShareAllocator::equal([5, 3, 9]))->toBe([3 => 3_334, 5 => 3_333, 9 => 3_333]);
});
