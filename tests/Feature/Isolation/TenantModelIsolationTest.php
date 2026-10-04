<?php

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Every Eloquent model under app/Models.
 *
 * @return array<int, class-string<Model>>
 */
function appModels(): array
{
    // Resolved at dataset-collection time, before the app boots — no app_path().
    return collect(glob(dirname(__DIR__, 3).'/app/Models/*.php'))
        ->map(fn (string $path) => 'App\\Models\\'.basename($path, '.php'))
        ->filter(fn (string $class) => is_subclass_of($class, Model::class))
        ->values()
        ->all();
}

/**
 * @return array<int, class-string<Model>>
 */
function tenantModels(): array
{
    return array_values(array_filter(
        appModels(),
        fn (string $class) => in_array(BelongsToTenant::class, class_uses_recursive($class), true),
    ));
}

dataset('tenant models', fn () => tenantModels());

it('never returns rows that belong to another tenant', function (string $model) {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
    $context = app(TenantContext::class);

    $model::factory()->create(['tenant_id' => $tenantB->id]);

    $context->set($tenantA);
    expect($model::count())->toBe(0);

    $context->set($tenantB);
    expect($model::count())->toBeGreaterThanOrEqual(1);

    $context->forget();
})->with('tenant models');

it('stamps new rows with the current tenant', function (string $model) {
    [$tenantA] = Tenant::factory()->count(1)->create();
    app(TenantContext::class)->set($tenantA);

    $record = $model::factory()->make(['tenant_id' => null]);
    $record->save();

    expect($record->tenant_id)->toBe($tenantA->id);

    app(TenantContext::class)->forget();
})->with('tenant models');

it('applies BelongsToTenant to every model whose table has a tenant_id column', function () {
    $unscoped = collect(appModels())
        ->reject(fn (string $class) => $class === Tenant::class)
        ->filter(fn (string $class) => Schema::hasColumn((new $class)->getTable(), 'tenant_id'))
        ->reject(fn (string $class) => in_array($class, tenantModels(), true))
        ->values()
        ->all();

    expect($unscoped)->toBe([]);
});
