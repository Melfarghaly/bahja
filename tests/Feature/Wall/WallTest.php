<?php

use App\Enums\AuditAction;
use App\Enums\NotificationType;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\MediaConsent;
use App\Models\Moment;
use App\Models\MomentMedia;
use App\Models\PushDevice;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\GuardianService;
use App\Services\Notifications\Push\PushGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePushGateway;

beforeEach(function () {
    Storage::fake('local');
    $this->push = new FakePushGateway;
    $this->app->instance(PushGateway::class, $this->push);
    $this->travelTo(now()->setTimezone('Africa/Cairo')->setTime(10, 0)->utc());

    [$this->tenant, $this->owner] = createNurseryWithOwner(['name' => 'حضانة البراعم']);
    enableDailyWall($this->tenant);
    $this->teacher = attachTeacher($this->tenant, User::factory()->create(['name' => 'مس هالة']));
    $this->classroom = Classroom::factory()->create(['tenant_id' => $this->tenant->id]);

    $make = fn (string $name) => Child::factory()->create(['tenant_id' => $this->tenant->id, 'classroom_id' => $this->classroom->id, 'first_name' => $name]);
    $this->yousef = $make('يوسف');
    $this->layla = $make('ليلى');
    $this->omar = $make('عمر');

    $this->mother = User::factory()->create(['name' => 'منى']);     // Yousef + Layla
    $this->omarsMom = User::factory()->create(['name' => 'سارة']);
    $this->father = User::factory()->create(['name' => 'أحمد']);    // custody-blocked
    $this->driver = User::factory()->create(['name' => 'عم سيد']);  // no wall access

    $g = app(GuardianService::class);
    $g->attach($this->yousef, $this->mother, ['relationship' => 'mother', 'role' => 'primary', 'can_view_wall' => true]);
    $g->attach($this->layla, $this->mother, ['relationship' => 'mother', 'role' => 'primary', 'can_view_wall' => true]);
    $g->attach($this->omar, $this->omarsMom, ['relationship' => 'mother', 'role' => 'primary', 'can_view_wall' => true]);
    $g->attach($this->yousef, $this->father, ['relationship' => 'father', 'role' => 'viewer', 'can_view_wall' => true, 'custody_flag' => 'blocked']);
    $g->attach($this->yousef, $this->driver, ['relationship' => 'driver', 'role' => 'pickup_authorized', 'can_view_wall' => false]);

    foreach ([$this->mother, $this->omarsMom, $this->father, $this->driver] as $user) {
        PushDevice::factory()->create(['user_id' => $user->id]);
    }

    $this->asTeacher = fn () => $this->actingAs($this->teacher)->withHeader('X-Tenant-Id', $this->tenant->id);
    $this->asGuardian = fn (User $user) => $this->actingAs($user)->withHeader('X-Tenant-Id', $this->tenant->id);
});

it('posts one update for a whole class, minus exceptions, and tells each family once', function () {
    ($this->asTeacher)()->postJson('/api/v1/moments', [
        'type' => 'meal', 'classroom_id' => $this->classroom->id, 'except_child_ids' => [$this->omar->id],
        'payload' => ['meal' => 'lunch', 'amount' => 'half', 'ignored' => 'x'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.summary', 'وجبة الغداء: أكل نصفه')
        ->assertJsonPath('data.payload', ['meal' => 'lunch', 'amount' => 'half'])
        ->assertJsonCount(2, 'data.children')
        ->assertJsonPath('data.author.name', 'مس هالة');

    $inbox = UserNotification::withoutGlobalScopes()->get();
    expect($inbox)->toHaveCount(1)                                   // the mother, once for both children
        ->and($inbox[0]->user_id)->toBe($this->mother->id)
        ->and($inbox[0]->type)->toBe(NotificationType::MomentPosted)
        ->and($inbox[0]->render()['title'])->toBe('تحديث جديد عن يوسف وليلى')
        ->and($inbox[0]->render()['body'])->toBe('مس هالة: وجبة الغداء: أكل نصفه');
});

it('validates each kind of update', function () {
    ($this->asTeacher)()->postJson('/api/v1/moments', ['type' => 'meal', 'child_ids' => [$this->yousef->id], 'payload' => ['meal' => 'lunch']])
        ->assertUnprocessable()->assertJsonValidationErrors('payload.amount');
    ($this->asTeacher)()->postJson('/api/v1/moments', ['type' => 'nap', 'child_ids' => [$this->yousef->id], 'payload' => ['from' => '13:00', 'to' => '12:00']])
        ->assertUnprocessable()->assertJsonValidationErrors('payload.to');
    ($this->asTeacher)()->postJson('/api/v1/moments', ['type' => 'note', 'child_ids' => [$this->yousef->id], 'classroom_id' => $this->classroom->id, 'body' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('child_ids');
    ($this->asTeacher)()->postJson('/api/v1/moments', ['type' => 'photo', 'child_ids' => [$this->yousef->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('photos');
    ($this->asGuardian)($this->mother)->postJson('/api/v1/moments', ['type' => 'note', 'child_ids' => [$this->yousef->id], 'body' => 'x'])
        ->assertForbidden();
});

it('refuses photos of a child without the family photo consent', function () {
    ($this->asTeacher)()->post('/api/v1/moments', ['type' => 'photo', 'child_ids' => [$this->yousef->id], 'photos' => [UploadedFile::fake()->image('a.jpg', 800, 600)]], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.child_ids.0', 'لا يوجد إذن تصوير لـ: يوسف');

    grantPhotoConsent($this->yousef, group: false);
    ($this->asTeacher)()->post('/api/v1/moments', ['type' => 'photo', 'child_ids' => [$this->yousef->id, $this->omar->id], 'photos' => [UploadedFile::fake()->image('a.jpg', 800, 600)]], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.child_ids.0', 'لا يوجد إذن بالظهور في الصور الجماعية لـ: يوسف، عمر');

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('strips EXIF, turns the photo upright, and serves it only through expiring signed links', function () {
    grantPhotoConsent($this->yousef);

    $response = ($this->asTeacher)()->post('/api/v1/moments', [
        'type' => 'photo', 'child_ids' => [$this->yousef->id], 'body' => 'يوم الرسم',
        'photos' => [jpegWithExif(300, 200, orientation: 6)],
    ], ['Accept' => 'application/json'])->assertCreated();

    $photo = $response->json('data.photos.0');
    expect($photo['width'])->toBe(200)->and($photo['height'])->toBe(300);   // rotated upright

    $media = MomentMedia::withoutGlobalScopes()->sole();
    $stored = Storage::disk('local')->get($media->path);
    expect($stored)->not->toContain('Exif')->not->toContain('SpyPhone')
        ->and(getimagesizefromstring(Storage::disk('local')->get($media->thumb_path))[1])->toBeLessThanOrEqual(480)
        ->and($media->path)->toStartWith("wall/{$this->tenant->id}/");

    auth()->forgetGuards();
    $this->get($photo['url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get($photo['thumb_url'])->assertOk();
    $this->get(preg_replace('/signature=\w+/', 'signature=forged', $photo['url']))->assertForbidden();
    $this->get(strtok($photo['url'], '?'))->assertForbidden();

    $this->travel(31)->minutes();
    $this->get($photo['url'])->assertForbidden();
});

it('hides a group photo from other families once a child in it loses group consent', function () {
    grantPhotoConsent($this->yousef);
    grantPhotoConsent($this->omar);
    ($this->asTeacher)()->post('/api/v1/moments', ['type' => 'photo', 'child_ids' => [$this->yousef->id, $this->omar->id], 'photos' => [UploadedFile::fake()->image('a.jpg', 800, 600)]], ['Accept' => 'application/json'])->assertCreated();

    ($this->asGuardian)($this->omarsMom)->getJson("/api/v1/me/wards/{$this->omar->id}/moments")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.children', [['id' => $this->omar->id, 'first_name' => 'عمر', 'acknowledged_at' => null]]);   // never the other child

    ($this->asGuardian)($this->mother)->putJson("/api/v1/me/wards/{$this->yousef->id}/photo-consent", ['group_photos' => false])
        ->assertOk()->assertJsonPath('data', ['wall' => true, 'group_photos' => false, 'can_change' => true]);

    ($this->asGuardian)($this->omarsMom)->getJson("/api/v1/me/wards/{$this->omar->id}/moments")->assertJsonCount(0, 'data');
    ($this->asGuardian)($this->mother)->getJson("/api/v1/me/wards/{$this->yousef->id}/moments")->assertJsonCount(1, 'data');
});

it('keeps the wall to families who may see it', function () {
    ($this->asTeacher)()->postJson('/api/v1/moments', ['type' => 'note', 'child_ids' => [$this->yousef->id], 'body' => 'رسم اليوم شمساً'])->assertCreated();

    ($this->asGuardian)($this->mother)->getJson("/api/v1/me/wards/{$this->yousef->id}/moments")->assertOk()->assertJsonPath('data.0.body', 'رسم اليوم شمساً');
    ($this->asGuardian)($this->driver)->getJson("/api/v1/me/wards/{$this->yousef->id}/moments")->assertForbidden();
    ($this->asGuardian)($this->father)->getJson("/api/v1/me/wards/{$this->yousef->id}/moments")->assertNotFound();
    ($this->asGuardian)($this->omarsMom)->getJson("/api/v1/me/wards/{$this->yousef->id}/moments")->assertNotFound();

    expect(UserNotification::withoutGlobalScopes()->pluck('user_id')->all())->toBe([$this->mother->id]);
});

it('enforces the Free plan daily photo allowance per child', function () {
    grantPhotoConsent($this->yousef);
    $post = fn (int $photos) => ($this->asTeacher)()->post('/api/v1/moments', [
        'type' => 'photo', 'child_ids' => [$this->yousef->id],
        'photos' => array_map(fn () => UploadedFile::fake()->image('p.jpg', 400, 300), range(1, $photos)),
    ], ['Accept' => 'application/json']);

    $post(2)->assertCreated();
    $post(2)->assertStatus(402)->assertJsonPath('code', 'plan_limit_reached');
    $post(1)->assertCreated();

    $this->travel(1)->day();
    $post(3)->assertCreated();
});

it('sends incidents urgently and records the family acknowledgement', function () {
    $this->tenant->update(['settings' => ['quiet_hours_start' => '00:00', 'quiet_hours_end' => '23:59']]);   // quiet all day
    $id = ($this->asTeacher)()->postJson('/api/v1/moments', ['type' => 'incident', 'child_ids' => [$this->yousef->id], 'body' => 'تعثّر في الحديقة وخدش ركبته، تم تطهير الجرح.'])
        ->assertCreated()->assertJsonPath('data.requires_ack', true)->json('data.id');

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['message']->urgent)->toBeTrue()
        ->and($this->push->sent[0]['message']->body)->not->toContain('ركبته');   // details stay in the app

    ($this->asGuardian)($this->mother)->postJson("/api/v1/me/wards/{$this->yousef->id}/moments/{$id}/acknowledge")
        ->assertOk()->assertJsonPath('data.children.0.acknowledged_at', now()->toIso8601String());
    ($this->asGuardian)($this->mother)->postJson("/api/v1/me/wards/{$this->yousef->id}/moments/{$id}/acknowledge")->assertOk();

    ($this->asTeacher)()->getJson('/api/v1/moments?child_id='.$this->yousef->id)
        ->assertJsonPath('data.0.children.0.acknowledged_at', now()->toIso8601String());

    $note = ($this->asTeacher)()->postJson('/api/v1/moments', ['type' => 'note', 'child_ids' => [$this->yousef->id], 'body' => 'x'])->json('data.id');
    ($this->asGuardian)($this->mother)->postJson("/api/v1/me/wards/{$this->yousef->id}/moments/{$note}/acknowledge")->assertUnprocessable();
});

it('lets a teacher take back her update for a day, and erases its photos', function () {
    grantPhotoConsent($this->yousef);
    $response = ($this->asTeacher)()->post('/api/v1/moments', ['type' => 'photo', 'child_ids' => [$this->yousef->id], 'photos' => [UploadedFile::fake()->image('a.jpg', 400, 300)]], ['Accept' => 'application/json']);
    $url = $response->json('data.photos.0.url');
    $id = $response->json('data.id');

    ($this->asGuardian)($this->mother)->deleteJson("/api/v1/moments/{$id}")->assertForbidden();
    ($this->asTeacher)()->deleteJson("/api/v1/moments/{$id}")->assertNoContent();

    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Moment::withTrashed()->find($id)->trashed())->toBeTrue()
        ->and(AuditLog::withoutGlobalScopes()->where('action', AuditAction::MomentDeleted)->count())->toBe(1);
    auth()->forgetGuards();
    $this->get($url)->assertNotFound();

    $old = ($this->asTeacher)()->postJson('/api/v1/moments', ['type' => 'note', 'child_ids' => [$this->yousef->id], 'body' => 'x'])->json('data.id');
    $this->travel(25)->hours();
    ($this->asTeacher)()->deleteJson("/api/v1/moments/{$old}")->assertForbidden();
    $this->actingAs($this->owner)->withHeader('X-Tenant-Id', $this->tenant->id)->deleteJson("/api/v1/moments/{$old}")->assertNoContent();
});

it('lets only a primary guardian change photo consent', function () {
    ($this->asGuardian)($this->mother)->getJson("/api/v1/me/wards/{$this->yousef->id}/photo-consent")
        ->assertOk()->assertJsonPath('data', ['wall' => false, 'group_photos' => false, 'can_change' => true]);

    ($this->asGuardian)($this->mother)->putJson("/api/v1/me/wards/{$this->yousef->id}/photo-consent", ['wall' => true, 'group_photos' => true])
        ->assertOk()->assertJsonPath('data.group_photos', true);

    $grandma = User::factory()->create();
    app(GuardianService::class)->attach($this->yousef, $grandma, ['relationship' => 'grandparent', 'role' => 'viewer', 'can_view_wall' => true]);
    ($this->asGuardian)($grandma)->getJson("/api/v1/me/wards/{$this->yousef->id}/photo-consent")->assertJsonPath('data.can_change', false);
    ($this->asGuardian)($grandma)->putJson("/api/v1/me/wards/{$this->yousef->id}/photo-consent", ['wall' => false])->assertForbidden();

    ($this->asTeacher)()->getJson("/api/v1/children/{$this->yousef->id}")->assertJsonPath('data.photo_consent', ['wall' => true, 'group_photos' => true]);
    expect(MediaConsent::withoutGlobalScopes()->count())->toBe(2)
        ->and(AuditLog::withoutGlobalScopes()->where('action', AuditAction::MediaConsentGranted)->count())->toBe(2);
});

it('erases photos past the plan retention but keeps the moment', function () {
    grantPhotoConsent($this->yousef);
    ($this->asTeacher)()->post('/api/v1/moments', ['type' => 'photo', 'child_ids' => [$this->yousef->id], 'body' => 'رحلة', 'photos' => [UploadedFile::fake()->image('a.jpg', 400, 300)]], ['Accept' => 'application/json'])->assertCreated();

    $this->travel(29)->days();
    $this->artisan('wall:prune-media')->assertSuccessful();
    expect(MomentMedia::withoutGlobalScopes()->count())->toBe(1);

    $this->travel(2)->days();
    $this->artisan('wall:prune-media')->assertSuccessful();
    expect(MomentMedia::withoutGlobalScopes()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Moment::withoutGlobalScopes()->sole())->media_count->toBe(0)->body->toBe('رحلة');
});

it('keeps the wall hidden until released to the nursery', function () {
    [$other, $otherOwner] = createNurseryWithOwner();
    $this->actingAs($otherOwner)->withHeader('X-Tenant-Id', $other->id)->getJson('/api/v1/moments')->assertNotFound();
});
