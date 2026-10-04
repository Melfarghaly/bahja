<?php

use App\Http\Middleware\EnforcePlanQuota;
use App\Http\Middleware\EnsureNurseryAdmin;
use App\Http\Middleware\EnsureNurseryStaff;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            'super-admin' => EnsureSuperAdmin::class,
            'nursery.staff' => EnsureNurseryStaff::class,
            'nursery.admin' => EnsureNurseryAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
