<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the request and stores it in TenantContext so
 * the global TenantScope can isolate every query. Resolution order:
 *   1. Explicit `tenant` route/query parameter the user is a member of.
 *   2. The user's first tenant membership.
 * In Phase 2 this is where teacher context-switching between nurseries happens.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401, 'Unauthenticated.');

        $tenantId = $request->route('tenant') ?? $request->header('X-Tenant-Id');

        $tenant = $tenantId !== null
            ? $user->tenants()->where('tenants.id', $tenantId)->first()
            : $user->tenants()->first();

        abort_if(! $tenant instanceof Tenant, 403, 'No accessible tenant context.');

        app(TenantContext::class)->set($tenant);

        return $next($request);
    }
}
