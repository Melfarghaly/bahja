<?php

namespace App\Http\Middleware;

use App\Enums\Limit;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks an action when it would exceed the nursery's quota for a resource.
 * Usage: ->middleware('plan.quota:children') — any App\Enums\Limit value.
 */
class EnforcePlanQuota
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $tenant = $this->tenantContext->get();

        // Throws PlanLimitException, rendered by the API as 402 plan_limit_reached.
        if ($tenant !== null) {
            $this->subscriptions->assertCanAdd($tenant, Limit::from($resource));
        }

        return $next($request);
    }
}
