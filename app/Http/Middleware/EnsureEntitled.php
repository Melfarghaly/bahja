<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Services\EntitlementService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a commercial feature of the nursery's plan.
 * Usage: ->middleware('entitled:auto_collection') — any App\Enums\Feature value.
 * Must run after the `tenant` middleware.
 */
class EnsureEntitled
{
    public function __construct(
        private EntitlementService $entitlements,
        private TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $this->tenantContext->get();

        abort_if($tenant === null, 403, 'No tenant context.');

        abort_unless(
            $this->entitlements->for($tenant)->allows(Feature::from($feature)),
            Response::HTTP_PAYMENT_REQUIRED,
            'هذه الميزة غير متاحة في خطتك الحالية. يرجى الترقية.',
        );

        return $next($request);
    }
}
