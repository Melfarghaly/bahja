<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant context for signed public links (no signed-in user), taken from the
 * raw {tenant} route parameter. Runs before route model binding — like
 * IdentifyTenant — so every bound model is resolved inside the nursery.
 * Only ever use behind the `signed` middleware.
 */
class IdentifyTenantFromRoute
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::find((int) $request->route()->originalParameter('tenant'));

        abort_if($tenant === null, 404);

        $this->tenantContext->set($tenant);

        return $next($request);
    }
}
