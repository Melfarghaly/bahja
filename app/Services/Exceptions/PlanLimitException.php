<?php

namespace App\Services\Exceptions;

use App\Enums\Limit;
use RuntimeException;

/**
 * Thrown when an action would exceed the current subscription plan's quota.
 */
class PlanLimitException extends RuntimeException
{
    public function __construct(public string $resource, public int $limit)
    {
        parent::__construct(__('api.errors.plan_limit', [
            'limit' => $limit,
            'resource' => Limit::tryFrom($resource)?->label() ?? $resource,
        ]));
    }
}
