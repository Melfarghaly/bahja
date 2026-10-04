<?php

namespace App\Http\Middleware;

use App\Enums\RolloutFlag;
use App\Services\RolloutService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides a module from nurseries it has not been released to yet (404, as if
 * the route did not exist). Usage: ->middleware('rollout:bahga-pay').
 * Must run after the `tenant` middleware.
 */
class EnsureRolledOut
{
    public function __construct(
        private RolloutService $rollouts,
        private TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string $flag): Response
    {
        $tenant = $this->tenantContext->get();

        abort_unless($tenant !== null && $this->rollouts->active($tenant, RolloutFlag::from($flag)), 404);

        return $next($request);
    }
}
