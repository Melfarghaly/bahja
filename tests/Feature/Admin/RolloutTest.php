<?php

use App\Enums\AuditAction;
use App\Enums\RolloutFlag;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RolloutService;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Feature;

function rolloutActive(Tenant $tenant, RolloutFlag $flag): bool
{
    Feature::flushCache();

    return app(RolloutService::class)->active($tenant, $flag);
}

it('keeps V2 modules off by default', function () {
    $tenant = Tenant::factory()->create();

    expect(rolloutActive($tenant, RolloutFlag::BahgaPay))->toBeFalse();
});

it('switches a module on for a single pilot nursery', function () {
    $admin = User::factory()->superAdmin()->create();
    [$pilot, $other] = Tenant::factory()->count(2)->create();

    $this->actingAs($admin)
        ->patch(route('admin.nurseries.rollouts.update', [$pilot, RolloutFlag::BahgaPay]), ['active' => 1])
        ->assertSessionHasNoErrors();

    expect(rolloutActive($pilot, RolloutFlag::BahgaPay))->toBeTrue()
        ->and(rolloutActive($other, RolloutFlag::BahgaPay))->toBeFalse()
        ->and(AuditLog::withoutGlobalScopes()->sole()->action)->toBe(AuditAction::RolloutChanged);
});

it('releases a module to every nursery, including ones resolved earlier and new ones', function () {
    $admin = User::factory()->superAdmin()->create();
    $existing = Tenant::factory()->create();
    expect(rolloutActive($existing, RolloutFlag::DailyWall))->toBeFalse(); // stored as off

    $this->actingAs($admin)
        ->patch(route('admin.rollouts.everyone', RolloutFlag::DailyWall), ['active' => 1])
        ->assertSessionHasNoErrors();

    $newcomer = Tenant::factory()->create();

    expect(rolloutActive($existing, RolloutFlag::DailyWall))->toBeTrue()
        ->and(rolloutActive($newcomer, RolloutFlag::DailyWall))->toBeTrue();

    $this->actingAs($admin)->patch(route('admin.rollouts.everyone', RolloutFlag::DailyWall), ['active' => 0]);

    expect(rolloutActive($existing, RolloutFlag::DailyWall))->toBeFalse();
});

it('hides an unreleased module behind a 404', function () {
    Route::middleware(['web', 'auth', 'tenant', 'rollout:bahga-pay'])->get('/_test/pay', fn () => 'ok');
    [$tenant, $owner] = createNurseryWithOwner();

    $this->actingAs($owner)->get('/_test/pay')->assertNotFound();

    Feature::for($tenant)->activate(RolloutFlag::BahgaPay->value);

    $this->actingAs($owner)->get('/_test/pay')->assertOk();
});

it('rejects unknown flags and non super admins', function () {
    [$tenant, $owner] = createNurseryWithOwner();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->patch('/admin/rollouts/not-a-flag', ['active' => 1])->assertNotFound();
    $this->actingAs($owner)->patch(route('admin.rollouts.everyone', RolloutFlag::BahgaPay), ['active' => 1])->assertForbidden();
});

it('renders the rollout pages', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)->get(route('admin.rollouts.index'))->assertOk()->assertSee(RolloutFlag::BahgaPay->label());
    $this->actingAs($admin)->get(route('admin.nurseries.show', $tenant))->assertOk()->assertSee('الإطلاق التدريجي لوحدات V2');
});
