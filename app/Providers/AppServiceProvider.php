<?php

namespace App\Providers;

use App\Enums\Feature;
use App\Enums\RolloutFlag;
use App\Services\EntitlementService;
use App\Services\Messaging\LogSmsGateway;
use App\Services\Messaging\SmsGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymobGateway;
use App\Services\RolloutService;
use App\Support\RowLevelSecurity;
use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature as Pennant;

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

        // Outgoing SMS. Add a provider driver here when one is contracted.
        $this->app->bind(SmsGateway::class, fn () => match (config('services.sms.driver')) {
            default => new LogSmsGateway,
        });
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

        // Rollout flags (Pennant) are scoped to the current nursery.
        Pennant::resolveScopeUsing(fn () => $this->app->make(TenantContext::class)->get());

        foreach (RolloutFlag::cases() as $flag) {
            Pennant::define($flag->value, fn (mixed $scope) => $this->app->make(RolloutService::class)->resolve($flag, $scope));
        }

        // @rolledout('bahga-pay') ... @endrolledout — false outside a tenant context.
        Blade::if('rolledout', function (string $flag): bool {
            $tenant = $this->app->make(TenantContext::class)->get();

            return $tenant !== null
                && $this->app->make(RolloutService::class)->active($tenant, RolloutFlag::from($flag));
        });

        // API: 120 requests/minute per signed-in user (or per IP for guests).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Separate counters per action, so a few failed password logins don't
        // also block requesting an SMS code (numeric throttles share one key).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(6)->by('login|'.$request->ip()));
        RateLimiter::for('otp-send', fn (Request $request) => Limit::perMinute(6)->by('otp-send|'.$request->ip()));
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by('otp-verify|'.$request->ip()));
        // Pass codes are 6 digits: limit guessing by any one staff account.
        RateLimiter::for('pickup-verify', fn (Request $request) => Limit::perMinute(30)->by('pickup-verify|'.($request->user()?->id ?: $request->ip())));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by('checkout|'.($request->user()?->id ?: $request->ip())));

        // Queue workers reuse one process and DB session across jobs: start each
        // job with no tenant and no RLS bypass so nothing carries over.
        Queue::before(function (): void {
            $this->app->make(TenantContext::class)->forget();
            $this->app->make(RowLevelSecurity::class)->disableBypass();
        });
    }
}
