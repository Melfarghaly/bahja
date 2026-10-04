<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user is an owner/admin of the current tenant.
 * Used for management screens (teachers, classrooms, billing, settings).
 */
class EnsureNurseryAdmin
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $this->tenantContext->get();

        abort_if($user === null || $tenant === null, 403);
        abort_unless($user->manages($tenant), 403, 'Nursery admin access required.');

        return $next($request);
    }
}
