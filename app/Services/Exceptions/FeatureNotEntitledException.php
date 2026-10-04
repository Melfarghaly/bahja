<?php

namespace App\Services\Exceptions;

use App\Enums\Feature;
use RuntimeException;

/**
 * Thrown when the nursery's plan (and add-ons/overrides) do not include a feature.
 */
class FeatureNotEntitledException extends RuntimeException
{
    public function __construct(public Feature $feature)
    {
        parent::__construct("Feature [{$feature->value}] is not included in the current plan.");
    }
}
