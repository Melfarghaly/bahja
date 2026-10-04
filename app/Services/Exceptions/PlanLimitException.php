<?php

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Thrown when an action would exceed the current subscription plan's quota.
 */
class PlanLimitException extends RuntimeException
{
    public function __construct(public string $resource, public int $limit)
    {
        parent::__construct("Plan limit reached for [{$resource}]: max {$limit}.");
    }
}
