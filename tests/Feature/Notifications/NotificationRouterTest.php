<?php

use App\Enums\NotificationType;
use App\Enums\PushStatus;
use App\Models\Child;
use App\Models\NotificationDelivery;
use App\Models\PushDevice;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AttendanceService;
use App\Services\GuardianService;
use App\Services\Messaging\SmsGateway;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationRouter;
use App\Services\Notifications\Push\PushGateway;
use Carbon\CarbonImmutable;
use Tests\Support\FakePushGateway;
use Tests\Support\FakeSmsGateway;

function cairoAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-10-05 {$time}", 'Africa/Cairo');
}

beforeEach(function () {
    $this->push = new FakePushGateway;
    $this->sms = new FakeSmsGateway;
    $this->app->instance(PushGateway::class, $this->push);
    $this->app->instance(SmsGateway::class, $this->sms);

    $this->travelTo(cairoAt('08:00'));

    [$this->tenant, $this->owner] = createNurseryWithOwner(['name' => 'حضانة البراعم']);
    enableMessagingHub($this->tenant);
    $this->teacher = attachTeacher($this->tenant);

    $this->yousef = Child::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'يوسف']);
    $this->mother = User::factory()->create(['name' => 'منى', 'phone' => '01033334444']);
    $this->father = User::factory()->create(['name' => 'أحمد', 'phone' => '01055556666']);
    $this->grandma = User::factory()->create(['name' => 'الجدة', 'phone' => '01077778888']);
    $this->driver = User::factory()->create(['name' => 'عم سيد', 'phone' => '01099998888']);

    $g = app(GuardianService::class);
    $g->attach($this->yousef, $this->mother, ['relationship' => 'mother', 'role' => 'primary', 'can_pickup' => true]);
    $g->attach($this->yousef, $this->father, ['relationship' => 'father', 'role' => 'viewer', 'custody_flag' => 'blocked']);
    $g->attach($this->yousef, $this->grandma, ['relationship' => 'grandparent', 'role' => 'viewer']);
    $g->attach($this->yousef, $this->driver, ['relationship' => 'driver', 'role' => 'pickup_authorized', 'can_pickup' => true]);

    $this->motherPhone = PushDevice::factory()->create(['user_id' => $this->mother->id, 'token' => 'mother-android-token-0001']);
    $this->grandmaPhone = PushDevice::factory()->create(['user_id' => $this->grandma->id, 'token' => 'grandma-iphone-token-0001', 'platform' => 'ios', 'locale' => 'en']);
    PushDevice::factory()->create(['user_id' => $this->father->id, 'token' => 'father-android-token-0001']);
    PushDevice::factory()->create(['user_id' => $this->driver->id, 'token' => 'driver-android-token-0001']);
});

function pushedTokens(FakePushGateway $push): array
{
    return collect($push->sent)->pluck('token')->sort()->values()->all();
}

it('tells the family the child arrived, in each device language, never a blocked parent or the driver', function () {
    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);

    expect(pushedTokens($this->push))->toBe(['grandma-iphone-token-0001', 'mother-android-token-0001']);

    $byToken = collect($this->push->sent)->keyBy('token');
    expect($byToken['mother-android-token-0001']['message']->title)->toBe('وصل يوسف الحضانة')
        ->and($byToken['mother-android-token-0001']['message']->body)->toBe('وصل يوسف إلى حضانة البراعم الساعة 08:00.')
        ->and($byToken['grandma-iphone-token-0001']['message']->title)->toBe('يوسف arrived')
        ->and($byToken['mother-android-token-0001']['message']->data)->toMatchArray(['type' => 'child_arrived', 'child_id' => (string) $this->yousef->id, 'screen' => 'ward'])
        ->and($byToken['mother-android-token-0001']['message']->urgent)->toBeFalse();

    expect(UserNotification::withoutGlobalScopes()->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$this->mother->id, $this->grandma->id])->sort()->values()->all())
        ->and(NotificationDelivery::withoutGlobalScopes()->where('channel', 'push')->where('status', 'sent')->count())->toBe(2)
        ->and($this->sms->sent)->toBeEmpty();   // normal priority never costs an SMS
});

it('notifies once a day, not again when a check-in is corrected', function () {
    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);
    $this->travel(10)->minutes();
    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);

    expect($this->push->sent)->toHaveCount(2);
});

it('tells the rest of the family who picked the child up, but not the collector', function () {
    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);
    $this->push->sent = [];
    $this->travelTo(cairoAt('15:30'));

    app(AttendanceService::class)->checkOut($this->yousef, $this->mother, $this->teacher);

    expect(pushedTokens($this->push))->toBe(['grandma-iphone-token-0001'])
        ->and($this->push->sent[0]['message']->body)->toBe('يوسف left حضانة البراعم with منى at 15:30.');
});

it('reports a manager override to the whole family', function () {
    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);
    $this->push->sent = [];

    app(AttendanceService::class)->overrideCheckOut($this->yousef, $this->owner, 'خالة الطفل', 'طوارئ');

    expect(pushedTokens($this->push))->toBe(['grandma-iphone-token-0001', 'mother-android-token-0001'])
        ->and(collect($this->push->sent)->firstWhere('token', 'mother-android-token-0001')['message']->body)->toContain('خالة الطفل');
});

it('holds normal notifications during quiet hours and sends them when they end', function () {
    $this->travelTo(cairoAt('21:30'));
    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);

    expect($this->push->sent)->toBeEmpty()
        ->and(UserNotification::withoutGlobalScopes()->where('user_id', $this->mother->id)->sole())
        ->push_status->toBe(PushStatus::Deferred)
        ->deliver_after->toIso8601String()->toBe(cairoAt('07:00')->addDay()->utc()->toIso8601String());

    $this->travelTo(cairoAt('06:55')->addDay());
    $this->artisan('notifications:deliver-deferred')->assertSuccessful();
    expect($this->push->sent)->toBeEmpty();

    $this->travelTo(cairoAt('07:00')->addDay());
    $this->artisan('notifications:deliver-deferred')->assertSuccessful();
    $this->artisan('notifications:deliver-deferred')->assertSuccessful();   // never twice

    expect($this->push->sent)->toHaveCount(2)
        ->and(UserNotification::withoutGlobalScopes()->where('user_id', $this->mother->id)->sole()->push_status)->toBe(PushStatus::Sent);
});

it('uses the nursery quiet hours, including a window that does not cross midnight', function () {
    $this->tenant->update(['settings' => ['quiet_hours_start' => '13:00', 'quiet_hours_end' => '15:00']]);

    $this->travelTo(cairoAt('21:30'));
    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);
    expect($this->push->sent)->toHaveCount(2);    // 21:30 is not quiet for this nursery

    $this->travelTo(cairoAt('14:00'));
    app(AttendanceService::class)->overrideCheckOut($this->yousef, $this->owner, 'خالة الطفل', 'طوارئ');
    expect($this->push->sent)->toHaveCount(2);    // held until 15:00

    $this->tenant->update(['settings' => ['quiet_hours_start' => '00:00', 'quiet_hours_end' => '00:00']]);   // no quiet hours
    $this->yousef->unsetRelation('tenant');
    app(AttendanceService::class)->overrideCheckOut($this->yousef, $this->owner, 'خالة الطفل', 'طوارئ');
    expect($this->push->sent)->toHaveCount(4);
});

it('sends high-priority alerts through quiet hours, and falls back to SMS without an app', function () {
    $this->travelTo(cairoAt('22:00'));
    $router = app(NotificationRouter::class);

    $router->notify($this->tenant, $this->mother, NotificationType::LatePickup, ['nursery' => 'البراعم', 'child' => 'يوسف', 'deadline' => '16:00'], $this->yousef);
    expect(pushedTokens($this->push))->toBe(['mother-android-token-0001'])
        ->and($this->push->sent[0]['message']->urgent)->toBeTrue()
        ->and($this->sms->sent)->toBeEmpty();

    $this->motherPhone->delete();
    $router->notify($this->tenant, $this->mother, NotificationType::LatePickup, ['nursery' => 'البراعم', 'child' => 'يوسف', 'deadline' => '16:00'], $this->yousef);
    expect($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['phone'])->toBe('01033334444');
});

it('respects the guardian push and SMS choices per child', function () {
    $this->actingAs($this->mother)->withHeader('X-Tenant-Id', $this->tenant->id)
        ->patchJson("/api/v1/me/wards/{$this->yousef->id}/notifications", ['push' => false])
        ->assertOk()
        ->assertJsonPath('data.my_link.notifications', ['push' => false, 'sms' => true]);

    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);
    expect(pushedTokens($this->push))->toBe(['grandma-iphone-token-0001']);

    // A late pickup still reaches her (push off → SMS), unless SMS is off too.
    $router = app(NotificationRouter::class);
    $params = ['nursery' => 'البراعم', 'child' => 'يوسف', 'deadline' => '16:00'];
    $router->notify($this->tenant, $this->mother, NotificationType::LatePickup, $params, $this->yousef);
    expect($this->sms->sent)->toHaveCount(1);

    $this->actingAs($this->mother)->withHeader('X-Tenant-Id', $this->tenant->id)
        ->patchJson("/api/v1/me/wards/{$this->yousef->id}/notifications", ['sms' => false])->assertOk();
    $late = $router->notify($this->tenant, $this->mother, NotificationType::LatePickup, $params, $this->yousef);

    expect($this->sms->sent)->toHaveCount(1)
        ->and($late->fresh()->push_status)->toBe(PushStatus::Skipped);
});

it('never files anything about a child for a custody-blocked guardian', function () {
    $sent = app(NotificationRouter::class)->notify($this->tenant, $this->father, NotificationType::LatePickup, ['nursery' => 'x', 'child' => 'يوسف', 'deadline' => '16:00'], $this->yousef);

    expect($sent)->toBeNull()
        ->and(UserNotification::withoutGlobalScopes()->where('user_id', $this->father->id)->exists())->toBeFalse();
});

it('forgets a device whose app was uninstalled', function () {
    $this->push->unregistered = ['mother-android-token-0001'];

    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);

    expect(PushDevice::whereKey($this->motherPhone->id)->exists())->toBeFalse()
        ->and(UserNotification::withoutGlobalScopes()->where('user_id', $this->mother->id)->sole()->push_status)->toBe(PushStatus::Failed)
        ->and(NotificationDelivery::withoutGlobalScopes()->where('status', 'failed')->sole()->error)->toContain('UNREGISTERED');
});

it('delivers a notification only once', function () {
    app(AttendanceService::class)->checkIn($this->yousef, $this->teacher);
    $notification = UserNotification::withoutGlobalScopes()->where('user_id', $this->mother->id)->sole();

    app(NotificationDispatcher::class)->deliver($notification);

    expect($this->push->sent)->toHaveCount(2);
});

it('stays silent for nurseries without the Messaging Hub', function () {
    [$other] = createNurseryWithOwner();
    $child = Child::factory()->create(['tenant_id' => $other->id]);
    app(GuardianService::class)->attach($child, $this->mother, ['relationship' => 'mother', 'role' => 'primary']);

    app(AttendanceService::class)->checkIn($child, $this->teacher);

    expect($this->push->sent)->toBeEmpty()
        ->and(UserNotification::withoutGlobalScopes()->count())->toBe(0);
});
