<?php

namespace App\Providers;

use App\Enums\Feature;
use App\Services\EntitlementService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymobGateway;
use App\Support\RowLevelSecurity;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
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

        // Entitlements are memoized per request / queued job.
        $this->app->scoped(EntitlementService::class);

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

        // @entitled('white_label') ... @endentitled — false outside a tenant context.
        Blade::if('entitled', function (string $feature): bool {
            $tenant = $this->app->make(TenantContext::class)->get();

            return $tenant !== null
                && $this->app->make(EntitlementService::class)->for($tenant)->allows(Feature::from($feature));
        });

        // Queue workers reuse one process and DB session across jobs: start each
        // job with no tenant and no RLS bypass so nothing carries over.
        Queue::before(function (): void {
            $this->app->make(TenantContext::class)->forget();
            $this->app->make(RowLevelSecurity::class)->disableBypass();
        });
    }
}
