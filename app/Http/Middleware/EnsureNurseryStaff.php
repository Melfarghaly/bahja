<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user is staff (owner/admin/teacher) of the current
 * tenant. Runs after IdentifyTenant, which has already set the tenant context.
 */
class EnsureNurseryStaff
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $this->tenantContext->get();

        abort_if($user === null || $tenant === null, 403);
        abort_unless($user->manages($tenant) || $user->teachesIn($tenant), 403, 'Nursery staff access required.');

        return $next($request);
    }
}
