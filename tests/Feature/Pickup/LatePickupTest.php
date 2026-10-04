<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\LatePickupAlert;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\GuardianService;
use App\Services\Messaging\SmsGateway;
use Carbon\CarbonImmutable;
use Tests\Support\FakeSmsGateway;

function cairo(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-10-05 {$time}", 'Africa/Cairo');
}

beforeEach(function () {
    $this->sms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $this->sms);

    $this->travelTo(cairo('08:00'));

    [$this->tenant, $this->owner] = createNurseryWithOwner(['name' => 'حضانة البراعم', 'settings' => ['pickup_deadline' => '16:00']]);
    $this->owner->update(['phone' => '01011112222']);
    enableSafePickup($this->tenant);
    $this->teacher = attachTeacher($this->tenant);

    $this->yousef = Child::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'يوسف']);
    $this->layla = Child::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'ليلى']);
    $this->mother = User::factory()->create(['phone' => '01033334444']);
    $this->father = User::factory()->create(['phone' => '01055556666']);
    $this->grandma = User::factory()->create(['phone' => '01077778888']);

    $g = app(GuardianService::class);
    $g->attach($this->yousef, $this->mother, ['relationship' => 'mother', 'role' => 'primary', 'can_pickup' => true]);
    $g->attach($this->yousef, $this->father, ['relationship' => 'father', 'role' => 'viewer', 'can_pickup' => true, 'custody_flag' => 'blocked']);
    $g->attach($this->yousef, $this->grandma, ['relationship' => 'grandparent', 'role' => 'viewer', 'can_pickup' => false]);
    $g->attach($this->layla, $this->mother, ['relationship' => 'mother', 'role' => 'primary', 'can_pickup' => true]);

    foreach ([$this->yousef, $this->layla] as $child) {
        app(AttendanceService::class)->checkIn($child, $this->teacher);
    }
    app(AttendanceService::class)->checkOut($this->layla, $this->mother, $this->teacher);
});

function phonesTexted(FakeSmsGateway $sms): array
{
    return collect($sms->sent)->pluck('phone')->sort()->values()->all();
}

it('stays quiet before the guardian grace period ends', function () {
    $this->travelTo(cairo('16:14'));
    $this->artisan('pickup:late-alerts')->assertSuccessful();

    expect($this->sms->sent)->toBeEmpty();
});

it('texts guardians with pickup rights after 15 minutes, then managers after 45, once each', function () {
    $this->travelTo(cairo('16:15'));
    $this->artisan('pickup:late-alerts')->assertSuccessful();

    // Only the mother: the father is custody-blocked, the grandmother may not collect; Layla left already.
    expect(phonesTexted($this->sms))->toBe(['01033334444'])
        ->and($this->sms->sent[0]['message'])->toContain('يوسف')->toContain('16:00');

    $this->travelTo(cairo('16:30'));
    $this->artisan('pickup:late-alerts')->assertSuccessful();
    expect($this->sms->sent)->toHaveCount(1);   // no repeat

    $this->travelTo(cairo('16:45'));
    $this->artisan('pickup:late-alerts')->assertSuccessful();
    expect(phonesTexted($this->sms))->toBe(['01011112222', '01033334444']);

    $this->travelTo(cairo('17:00'));
    $this->artisan('pickup:late-alerts')->assertSuccessful();
    expect($this->sms->sent)->toHaveCount(2);

    expect(LatePickupAlert::withoutGlobalScopes()->orderBy('stage')->pluck('recipients', 'stage')->all())
        ->toBe(['guardians' => 1, 'managers' => 1])
        ->and(AuditLog::withoutGlobalScopes()->where('action', AuditAction::LatePickupAlerted)->count())->toBe(1);
});

it('sends both stages at once when the first run is already past 45 minutes', function () {
    $this->travelTo(cairo('17:10'));
    $this->artisan('pickup:late-alerts')->assertSuccessful();

    expect(phonesTexted($this->sms))->toBe(['01011112222', '01033334444']);
});

it('respects a guardian who turned SMS off', function () {
    $this->actingAs($this->mother)->patchJson("/api/v1/me/wards/{$this->yousef->id}/notifications", ['sms' => false])->assertOk();

    $this->travelTo(cairo('16:20'));
    $this->artisan('pickup:late-alerts')->assertSuccessful();

    expect($this->sms->sent)->toBeEmpty()
        ->and(LatePickupAlert::withoutGlobalScopes()->sole()->recipients)->toBe(0);
});

it('does nothing without a deadline or without Safe Pickup', function () {
    $this->tenant->update(['settings' => []]);
    [$other] = createNurseryWithOwner(['settings' => ['pickup_deadline' => '16:00']]);   // not released

    $this->travelTo(cairo('18:00'));
    $this->artisan('pickup:late-alerts')->assertSuccessful();

    expect($this->sms->sent)->toBeEmpty()
        ->and(LatePickupAlert::withoutGlobalScopes()->count())->toBe(0);
});

it('flags late children on the attendance sheet', function () {
    $this->travelTo(cairo('15:00'));
    $this->actingAs($this->teacher)->getJson('/api/v1/attendance')
        ->assertOk()
        ->assertJsonPath('pickup_deadline', '16:00')
        ->assertJsonPath('summary.late_pickup', 0);

    $this->travelTo(cairo('16:05'));
    $response = $this->actingAs($this->teacher)->getJson('/api/v1/attendance')->assertOk()->assertJsonPath('summary.late_pickup', 1);

    $rows = collect($response->json('data'))->keyBy('child.id');
    expect($rows[$this->yousef->id]['late_pickup'])->toBeTrue()
        ->and($rows[$this->layla->id]['late_pickup'])->toBeFalse();
});

it('lets the nursery set and clear its pickup deadline', function () {
    $this->actingAs($this->owner)
        ->put(route('nursery.settings.update'), ['name' => $this->tenant->name, 'pickup_deadline' => '25:00'])
        ->assertSessionHasErrors('pickup_deadline');

    $this->actingAs($this->owner)
        ->put(route('nursery.settings.update'), ['name' => $this->tenant->name, 'pickup_deadline' => '15:30'])
        ->assertSessionHasNoErrors();
    expect($this->tenant->fresh()->settings['pickup_deadline'])->toBe('15:30');

    $this->actingAs($this->owner)
        ->put(route('nursery.settings.update'), ['name' => $this->tenant->name, 'pickup_deadline' => ''])
        ->assertSessionHasNoErrors();
    expect($this->tenant->fresh()->settings)->not->toHaveKey('pickup_deadline');
});
