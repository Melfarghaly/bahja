<?php

use App\Enums\AuditAction;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\PickupPass;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\GuardianService;
use App\Services\Messaging\SmsGateway;
use Tests\Support\FakeSmsGateway;

beforeEach(function () {
    $this->sms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $this->sms);

    [$this->tenant, $this->owner] = createNurseryWithOwner(['name' => 'حضانة البراعم']);
    enableSafePickup($this->tenant);
    $this->teacher = attachTeacher($this->tenant);

    $this->yousef = Child::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'يوسف']);
    $this->layla = Child::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'ليلى']);
    $this->mother = User::factory()->create(['name' => 'منى']);
    $this->father = User::factory()->create(['name' => 'أحمد']);
    $this->viewer = User::factory()->create(['name' => 'الجدة']);

    $g = app(GuardianService::class);
    foreach ([$this->yousef, $this->layla] as $child) {
        $g->attach($child, $this->mother, ['relationship' => 'mother', 'role' => 'primary', 'can_pickup' => true]);
    }
    $g->attach($this->yousef, $this->father, ['relationship' => 'father', 'role' => 'viewer', 'can_pickup' => true, 'custody_flag' => 'blocked']);
    $g->attach($this->yousef, $this->viewer, ['relationship' => 'grandparent', 'role' => 'viewer', 'can_pickup' => false]);

    foreach ([$this->yousef, $this->layla] as $child) {
        app(AttendanceService::class)->checkIn($child, $this->teacher);
    }
});

function pickupToken($test, User $guardian): string
{
    return $test->actingAs($guardian)->getJson('/api/v1/me/pickup-code')->assertOk()->json('data.token');
}

it('gives a guardian a short-lived signed QR token', function () {
    $this->actingAs($this->mother)->getJson('/api/v1/me/pickup-code')
        ->assertOk()
        ->assertJsonPath('data.refresh_after', 30)
        ->assertJsonStructure(['data' => ['token', 'expires_at']]);

    $this->actingAs($this->viewer)->getJson('/api/v1/me/pickup-code')->assertForbidden();   // no pickup rights
});

it('shows the teacher who is at the door and what they may take', function () {
    $token = pickupToken($this, $this->mother);

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/pickup/verify', ['pickup_token' => $token])
        ->assertOk()
        ->assertJsonPath('data.collector.name', 'منى')
        ->assertJsonPath('data.collector.method', 'dynamic_qr')
        ->assertJsonCount(2, 'data.children')
        ->assertJsonPath('data.children.0.allowed', true)
        ->assertJsonPath('data.children.0.attendance_status', 'present');
});

it('flags a custody-blocked parent in red even with a valid QR', function () {
    // The father gets a QR while not blocked, then a custody order arrives: the block wins.
    $this->father->wards()->updateExistingPivot($this->yousef->id, ['custody_flag' => 'none']);
    $token = pickupToken($this, $this->father);
    $this->father->wards()->updateExistingPivot($this->yousef->id, ['custody_flag' => 'blocked']);

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/pickup/verify', ['pickup_token' => $token])
        ->assertOk()
        ->assertJsonPath('data.children.0.allowed', false)
        ->assertJsonPath('data.children.0.reason', 'custody_blocked');

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-out', ['child_id' => $this->yousef->id, 'pickup_token' => $token])
        ->assertForbidden()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'ممنوع الاستلام بحكم حضانة'));

    expect(AuditLog::withoutGlobalScopes()->where('action', AuditAction::PickupDenied)->latest('id')->first()->properties['reason'])->toBe('custody_blocked');
});

it('checks a child out by the guardian QR and records how', function () {
    $token = pickupToken($this, $this->mother);

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-out', ['child_id' => $this->layla->id, 'pickup_token' => $token])
        ->assertOk()
        ->assertJsonPath('data.pickup_method', 'dynamic_qr')
        ->assertJsonPath('data.picked_up_by', $this->mother->id);

    $attendance = Attendance::where('child_id', $this->layla->id)->sole();
    expect($attendance->checked_out_by)->toBe($this->teacher->id)->and($attendance->pickup_verified)->toBeTrue();
});

it('rejects expired, tampered and other-nursery QR tokens', function () {
    $token = pickupToken($this, $this->mother);

    $this->travel(61)->seconds();
    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/pickup/verify', ['pickup_token' => $token])
        ->assertUnprocessable()->assertJsonValidationErrors('pickup_token');

    $fresh = pickupToken($this, $this->mother);
    [$prefix, , $sig] = explode('.', $fresh);
    $payload = rtrim(strtr(base64_encode(json_encode(['g' => $this->father->id, 't' => $this->tenant->id, 'e' => now()->addHour()->timestamp])), '+/', '-_'), '=');
    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/pickup/verify', ['pickup_token' => "{$prefix}.{$payload}.{$sig}"])
        ->assertUnprocessable();

    [$other, $otherOwner] = createNurseryWithOwner();
    enableSafePickup($other);
    $this->actingAs($otherOwner)->withHeader('X-Tenant-Id', $other->id)
        ->postJson('/api/v1/attendance/pickup/verify', ['pickup_token' => $fresh])->assertUnprocessable();
});

it('lets a guardian issue a one-time pass that a teacher redeems once', function () {
    $response = $this->actingAs($this->mother)->postJson("/api/v1/me/wards/{$this->layla->id}/pickup-passes", [
        'name' => 'عم سيد السائق', 'phone' => '+201055554444', 'valid_until' => now()->addHours(5)->toDateTimeString(),
    ])->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.phone', '01055554444');

    $code = $response->json('code');
    expect($this->sms->sent[0]['phone'])->toBe('01055554444')
        ->and($this->sms->sent[0]['message'])->toContain($code)->toContain('ليلى');

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/pickup/verify', ['pass_code' => $code])
        ->assertOk()
        ->assertJsonPath('data.collector.name', 'عم سيد السائق')
        ->assertJsonPath('data.children.0.child.id', $this->layla->id)
        ->assertJsonPath('data.children.0.allowed', true);

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-out', ['child_id' => $this->yousef->id, 'pass_code' => $code])
        ->assertForbidden();   // a pass is for its own child only

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-out', ['child_id' => $this->layla->id, 'pass_code' => $code])
        ->assertOk()->assertJsonPath('data.pickup_method', 'pass_code');

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/pickup/verify', ['pass_code' => $code])
        ->assertUnprocessable()->assertJsonValidationErrors('pass_code');   // used

    expect(PickupPass::sole()->status())->toBe('used');
});

it('lets a guardian list and revoke passes, and refuses delegation without pickup rights', function () {
    $code = $this->actingAs($this->mother)->postJson("/api/v1/me/wards/{$this->layla->id}/pickup-passes", [
        'name' => 'خالة', 'phone' => '01066667777', 'valid_until' => now()->addHours(3)->toDateTimeString(),
    ])->json('code');
    $pass = PickupPass::sole();

    $this->actingAs($this->mother)->getJson("/api/v1/me/wards/{$this->layla->id}/pickup-passes")->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($this->mother)->deleteJson("/api/v1/me/pickup-passes/{$pass->id}")->assertOk()->assertJsonPath('data.status', 'revoked');
    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/pickup/verify', ['pass_code' => $code])->assertUnprocessable();

    $this->actingAs($this->viewer)->postJson("/api/v1/me/wards/{$this->yousef->id}/pickup-passes", [
        'name' => 'x', 'phone' => '01066667777', 'valid_until' => now()->addHour()->toDateTimeString(),
    ])->assertForbidden();
});

it('validates pass windows (max 24h, valid mobile)', function () {
    $this->actingAs($this->mother)->postJson("/api/v1/me/wards/{$this->layla->id}/pickup-passes", [
        'name' => 'x', 'phone' => '0223456789', 'valid_until' => now()->addDays(3)->toDateTimeString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['phone', 'valid_until']);
});

it('lets only managers override, with a reason, never marked as verified', function () {
    $body = ['child_id' => $this->yousef->id, 'collector_name' => 'عمّة الطفل', 'override_reason' => 'الأم في المستشفى واتصلت بالإدارة'];

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-out', $body)->assertForbidden();

    $this->actingAs($this->owner)->postJson('/api/v1/attendance/check-out', $body)
        ->assertOk()
        ->assertJsonPath('data.pickup_method', 'manual_override')
        ->assertJsonPath('data.pickup_verified', false)
        ->assertJsonPath('data.override_reason', 'عمّة الطفل: الأم في المستشفى واتصلت بالإدارة');

    expect(AuditLog::withoutGlobalScopes()->where('action', AuditAction::PickupOverridden)->count())->toBe(1);
});

it('requires exactly one way of identifying the collector', function () {
    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-out', ['child_id' => $this->layla->id])
        ->assertUnprocessable()->assertJsonValidationErrors('collector_id');

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-out', ['child_id' => $this->layla->id, 'collector_id' => $this->mother->id, 'pass_code' => '123456'])
        ->assertUnprocessable()->assertJsonValidationErrors(['collector_id', 'pass_code']);
});

it('records a bulk check-in, including offline timestamps', function () {
    $saleem = Child::factory()->create(['tenant_id' => $this->tenant->id]);
    $omar = Child::factory()->create(['tenant_id' => $this->tenant->id]);
    $earlier = now()->subMinutes(40)->startOfMinute();

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-in/bulk', [
        'method' => 'qr',
        'children' => [['child_id' => $saleem->id, 'checked_in_at' => $earlier->toIso8601String()], ['child_id' => $omar->id]],
    ])->assertOk()->assertJsonCount(2, 'data');

    expect(Attendance::where('child_id', $saleem->id)->sole()->checked_in_at->equalTo($earlier))->toBeTrue();

    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-in/bulk', [
        'children' => [['child_id' => $saleem->id, 'checked_in_at' => now()->addHour()->toIso8601String()], ['child_id' => $saleem->id]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['children.0.checked_in_at', 'children.0.child_id']);
});

it('keeps Safe Pickup hidden until released to the nursery', function () {
    [$other, $otherOwner] = createNurseryWithOwner();
    $this->actingAs($otherOwner)->withHeader('X-Tenant-Id', $other->id)
        ->postJson('/api/v1/attendance/pickup/verify', ['pass_code' => '123456'])->assertNotFound();
});

it('updates the same day record when a child is checked in again, whatever the device offset', function () {
    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-out', ['child_id' => $this->layla->id, 'collector_id' => $this->mother->id])->assertOk();

    $scannedAt = now()->subMinutes(10)->startOfMinute();
    $this->actingAs($this->teacher)->postJson('/api/v1/attendance/check-in/bulk', [
        'children' => [['child_id' => $this->layla->id, 'checked_in_at' => $scannedAt->copy()->setTimezone('Africa/Cairo')->toIso8601String()]],
    ])->assertOk();

    $attendance = Attendance::where('child_id', $this->layla->id)->sole();
    expect($attendance->checked_in_at->equalTo($scannedAt))->toBeTrue();
});
