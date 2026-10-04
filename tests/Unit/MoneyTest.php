<?php

use App\Support\Money;

it('parses pound amounts exactly, without floats', function (string $input, int $piasters) {
    expect(Money::fromPounds($input)->piasters)->toBe($piasters);
})->with([
    ['1250', 125_000],
    ['1250.5', 125_050],
    ['1250.05', 125_005],
    ['1,250.50', 125_050],
    ['0.01', 1],
    ['-10', -1_000],
]);

it('rejects malformed amounts', function (string $input) {
    Money::fromPounds($input);
})->with(['abc', '1.234', '12.', '', '1e3'])->throws(InvalidArgumentException::class);

it('takes percentages in basis points with half-up rounding', function () {
    expect(Money::of(1_000)->percentage(1_000)->piasters)->toBe(100)   // 10%
        ->and(Money::of(333)->percentage(5_000)->piasters)->toBe(167)  // 166.5 → 167
        ->and(Money::of(150_000)->percentage(3_500)->piasters)->toBe(52_500);
});

it('formats for humans and forms', function () {
    expect(Money::of(125_000)->format())->toBe('1,250 ج.م')
        ->and(Money::of(125_050)->format())->toBe('1,250.50 ج.م')
        ->and(Money::of(5)->toPounds())->toBe('0.05')
        ->and(Money::of(-1_050)->toPounds())->toBe('-10.50');
});

it('does arithmetic immutably', function () {
    $a = Money::of(500);
    $b = $a->plus(Money::of(250));

    expect($a->piasters)->toBe(500)
        ->and($b->piasters)->toBe(750)
        ->and($b->minus(Money::of(1_000))->piasters)->toBe(-250)
        ->and($b->min(Money::of(100))->piasters)->toBe(100);
});
