<?php

namespace App\Http\Middleware;

use App\Support\RowLevelSecurity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to platform super admins. Tenant context is intentionally
 * NOT set for these routes, so super admins operate across all tenants — which
 * is also the one place the Row-Level Security bypass is switched on.
 */
class EnsureSuperAdmin
{
    public function __construct(private RowLevelSecurity $rls) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);
        abort_unless($user->isSuperAdmin(), 403, 'Super admin access required.');

        $this->rls->enableBypass();

        return $next($request);
    }
}
