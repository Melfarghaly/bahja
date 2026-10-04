<?php

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Thrown when attempting to delete a subscription plan that still has tenants on it.
 */
class PlanInUseException extends RuntimeException
{
    public function __construct(public string $planName)
    {
        parent::__construct("Plan [{$planName}] is still in use and cannot be deleted.");
    }
}
