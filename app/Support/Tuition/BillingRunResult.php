<?php

namespace App\Support\Tuition;

use App\Support\Money;

/**
 * Outcome of generating one period's invoices for one nursery.
 */
final class BillingRunResult
{
    /**
     * @param  array<int, string>  $unbillableChildren  children with fees due but no payer guardian
     * @param  array<int, string>  $shareWarnings  children whose payers' shares don't add up to 100% (split equally)
     */
    public function __construct(
        public readonly int $created,
        public readonly int $alreadyInvoiced,
        public readonly array $unbillableChildren,
        public readonly int $totalBilledPiasters,
        public readonly array $shareWarnings = [],
    ) {}

    public function totalBilled(): Money
    {
        return Money::of($this->totalBilledPiasters);
    }
}
