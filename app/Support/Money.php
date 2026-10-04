<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An immutable EGP amount held as integer piasters (1 EGP = 100 piasters).
 * Never use floats for money: every calculation here is integer arithmetic,
 * and rounding is explicit (half up).
 */
final class Money implements JsonSerializable, Stringable
{
    private function __construct(public readonly int $piasters) {}

    public static function of(int $piasters): self
    {
        return new self($piasters);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parse a user-entered pound amount ("1250", "1250.5", "1,250.50") exactly.
     *
     * @throws InvalidArgumentException
     */
    public static function fromPounds(string|int $pounds): self
    {
        $value = str_replace([',', ' '], '', trim((string) $pounds));

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw new InvalidArgumentException("Invalid money amount [{$pounds}].");
        }

        $piasters = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return new self($m[1] === '-' ? -$piasters : $piasters);
    }

    public function plus(self $other): self
    {
        return new self($this->piasters + $other->piasters);
    }

    public function minus(self $other): self
    {
        return new self($this->piasters - $other->piasters);
    }

    /**
     * A share expressed in basis points (10000 = 100%), rounded half up.
     */
    public function percentage(int $basisPoints): self
    {
        return new self(intdiv($this->piasters * $basisPoints + 5000, 10000));
    }

    public function min(self $other): self
    {
        return $this->piasters <= $other->piasters ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->piasters === 0;
    }

    public function isPositive(): bool
    {
        return $this->piasters > 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->piasters > $other->piasters;
    }

    /**
     * Decimal pounds as a string, e.g. "1250.50" — for form inputs.
     */
    public function toPounds(): string
    {
        $sign = $this->piasters < 0 ? '-' : '';
        $abs = abs($this->piasters);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Human format: "1,250 ج.م" or "1,250.50 ج.م".
     */
    public function format(): string
    {
        $sign = $this->piasters < 0 ? '-' : '';
        $abs = abs($this->piasters);
        $pounds = number_format(intdiv($abs, 100));
        $rest = $abs % 100;

        return $sign.$pounds.($rest === 0 ? '' : '.'.str_pad((string) $rest, 2, '0', STR_PAD_LEFT)).' ج.م';
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function jsonSerialize(): array
    {
        return ['piasters' => $this->piasters, 'formatted' => $this->format()];
    }
}
