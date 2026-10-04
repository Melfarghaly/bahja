<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymobGateway;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant context per request lifecycle.
        $this->app->singleton(TenantContext::class);

        // Swap the gateway implementation here (Paymob / Fawry) without touching callers.
        $this->app->bind(PaymentGateway::class, PaymobGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Surface accidental N+1 queries during development.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
