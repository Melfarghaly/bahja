<?php

use App\Http\Middleware\EnforcePlanQuota;
use App\Http\Middleware\EnsureEntitled;
use App\Http\Middleware\EnsureNurseryAdmin;
use App\Http\Middleware\EnsureNurseryStaff;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'plan.quota' => EnforcePlanQuota::class,
            'entitled' => EnsureEntitled::class,
            'super-admin' => EnsureSuperAdmin::class,
            'nursery.staff' => EnsureNurseryStaff::class,
            'nursery.admin' => EnsureNurseryAdmin::class,
        ]);

        // The tenant context MUST be set before route model binding runs,
        // otherwise `{child}` etc. resolve without the TenantScope and leak
        // records across tenants.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: IdentifyTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
