<?php

use App\Enums\NotificationType;
use App\Models\Child;
use App\Models\PushDevice;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\GuardianService;

beforeEach(function () {
    [$this->tenant, $this->owner] = createNurseryWithOwner(['name' => 'حضانة البراعم']);
    $this->child = Child::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'يوسف']);
    $this->mother = User::factory()->create();
    app(GuardianService::class)->attach($this->child, $this->mother, ['relationship' => 'mother', 'role' => 'primary']);
});

function bearer(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('phone')->plainTextToken];
}

function inboxItem(array $attributes): UserNotification
{
    return UserNotification::factory()->create($attributes + [
        'type' => NotificationType::ChildArrived,
        'params' => ['child' => 'يوسف', 'nursery' => 'حضانة البراعم', 'time' => '08:05'],
    ]);
}

it('registers a device, refreshes it, and moves it when another account signs in', function () {
    $headers = bearer($this->mother);
    $body = ['token' => str_repeat('a', 40).':APA91b', 'platform' => 'android', 'locale' => 'ar', 'app_version' => '2.1.0'];

    $this->withHeaders($headers)->postJson('/api/v1/me/devices', $body)
        ->assertCreated()
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonMissingPath('data.token');

    $this->withHeaders($headers)->postJson('/api/v1/me/devices', ['locale' => 'en'] + $body)->assertOk()->assertJsonPath('data.locale', 'en');
    expect(PushDevice::count())->toBe(1);

    $father = User::factory()->create();
    $this->app['auth']->forgetGuards();
    $this->withHeaders(bearer($father))->postJson('/api/v1/me/devices', $body)->assertOk();
    expect(PushDevice::sole()->user_id)->toBe($father->id);
});

it('validates the device payload', function () {
    $this->withHeaders(bearer($this->mother))->postJson('/api/v1/me/devices', ['token' => 'short', 'platform' => 'blackberry'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'platform']);
});

it('stops notifying a device on sign-out or when the app asks', function () {
    $headers = bearer($this->mother);
    $this->withHeaders($headers)->postJson('/api/v1/me/devices', ['token' => str_repeat('b', 40), 'platform' => 'ios'])->assertCreated();
    $this->withHeaders($headers)->deleteJson('/api/v1/auth/tokens/current')->assertNoContent();
    expect(PushDevice::count())->toBe(0);

    $this->app['auth']->forgetGuards();
    $headers = bearer($this->mother);
    $this->withHeaders($headers)->postJson('/api/v1/me/devices', ['token' => str_repeat('c', 40), 'platform' => 'web'])->assertCreated();
    $this->withHeaders($headers)->deleteJson('/api/v1/me/devices', ['token' => str_repeat('c', 40)])->assertNoContent();
    expect(PushDevice::count())->toBe(0);
});

it('lists my inbox for this nursery with the unread count, in my language', function () {
    inboxItem(['tenant_id' => $this->tenant->id, 'user_id' => $this->mother->id, 'child_id' => $this->child->id]);
    inboxItem(['tenant_id' => $this->tenant->id, 'user_id' => $this->mother->id, 'read_at' => now()]);
    inboxItem(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);           // someone else's
    [$other] = createNurseryWithOwner();
    inboxItem(['tenant_id' => $other->id, 'user_id' => $this->mother->id]);                  // another nursery

    $this->actingAs($this->mother)->withHeader('X-Tenant-Id', $this->tenant->id)->getJson('/api/v1/me/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('data.1.title', 'وصل يوسف الحضانة')
        ->assertJsonPath('data.1.priority', 'normal')
        ->assertJsonPath('data.1.read', false);

    $this->actingAs($this->mother)->withHeaders(['X-Tenant-Id' => $this->tenant->id, 'Accept-Language' => 'en'])
        ->getJson('/api/v1/me/notifications?unread=1')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'يوسف arrived at حضانة البراعم at 08:05.');
});

it('marks one or all notifications read, and hides other people\'s', function () {
    $mine = inboxItem(['tenant_id' => $this->tenant->id, 'user_id' => $this->mother->id]);
    inboxItem(['tenant_id' => $this->tenant->id, 'user_id' => $this->mother->id]);
    $theirs = inboxItem(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $as = fn () => $this->actingAs($this->mother)->withHeader('X-Tenant-Id', $this->tenant->id);

    $as()->postJson("/api/v1/me/notifications/{$mine->id}/read")->assertOk()->assertJsonPath('data.read', true);
    $as()->postJson("/api/v1/me/notifications/{$theirs->id}/read")->assertNotFound();
    $as()->getJson('/api/v1/me/notifications')->assertJsonPath('unread_count', 1);

    $as()->postJson('/api/v1/me/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
    expect($theirs->fresh()->read_at)->toBeNull();
});

it('lets the nursery set its quiet hours', function () {
    $this->actingAs($this->owner)
        ->put(route('nursery.settings.update'), ['name' => $this->tenant->name, 'quiet_hours_start' => '22:00'])
        ->assertSessionHasErrors('quiet_hours_end');

    $this->actingAs($this->owner)
        ->put(route('nursery.settings.update'), ['name' => $this->tenant->name, 'quiet_hours_start' => '22:00', 'quiet_hours_end' => '06:30'])
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->settings)->toMatchArray(['quiet_hours_start' => '22:00', 'quiet_hours_end' => '06:30']);
    $this->actingAs($this->owner)->get(route('nursery.settings.edit'))->assertOk()->assertSee('ساعات الهدوء للإشعارات');
});
