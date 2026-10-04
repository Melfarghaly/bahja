<?php

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;

/*
| A queue worker must not leak one job's nursery into the next; a sync job
| (running inside a request) must not wipe the request's nursery either.
*/

it('starts every worker job with no nursery', function () {
    app(TenantContext::class)->set(Tenant::factory()->create());

    event(new JobProcessing('database', Mockery::mock(Job::class)->shouldIgnoreMissing()));

    expect(app(TenantContext::class)->has())->toBeFalse();
});

it('keeps the caller nursery for a job run synchronously', function () {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    event(new JobProcessing('sync', Mockery::mock(Job::class)->shouldIgnoreMissing()));

    expect(app(TenantContext::class)->id())->toBe($tenant->id);
});
