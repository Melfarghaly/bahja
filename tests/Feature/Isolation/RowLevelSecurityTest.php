<?php

use App\Models\Child;
use App\Models\Tenant;
use App\Support\RowLevelSecurity;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| These prove the database layer on its own: raw queries that never touch the
| Eloquent TenantScope are still confined to the pinned tenant.
*/

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Row-Level Security requires PostgreSQL.');
    }

    [$this->tenantA, $this->tenantB] = Tenant::factory()->count(2)->create();
    Child::factory()->create(['tenant_id' => $this->tenantA->id]);
    Child::factory()->count(2)->create(['tenant_id' => $this->tenantB->id]);

    $this->rls = app(RowLevelSecurity::class);
    $this->rls->disableBypass();
});

afterEach(function () {
    app(TenantContext::class)->forget();
});

it('confines raw queries to the pinned tenant', function () {
    app(TenantContext::class)->set($this->tenantA);

    expect(DB::table('children')->count())->toBe(1)
        ->and(DB::table('children')->where('tenant_id', $this->tenantB->id)->count())->toBe(0);
});

it('returns nothing when no tenant context is set', function () {
    expect(DB::table('children')->count())->toBe(0)
        ->and(Child::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects writing a row into another tenant', function () {
    app(TenantContext::class)->set($this->tenantA);

    // Savepoint, so the expected failure doesn't abort the test's transaction.
    expect(fn () => DB::transaction(fn () => Child::factory()->create(['tenant_id' => $this->tenantB->id])))
        ->toThrow(QueryException::class, 'row-level security');
});

it('sees every tenant only inside an explicit bypass', function () {
    expect($this->rls->bypass(fn () => DB::table('children')->count()))->toBe(3)
        ->and(DB::table('children')->count())->toBe(0);
});
