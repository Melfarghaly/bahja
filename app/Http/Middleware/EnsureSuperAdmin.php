<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to platform super admins. Tenant context is intentionally
 * NOT set for these routes, so super admins operate across all tenants.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);
        abort_unless($user->isSuperAdmin(), 403, 'Super admin access required.');

        return $next($request);
    }
}
