<?php

use App\Models\Child;
use Illuminate\Support\Facades\Route;

it('answers 401 with the unified shape', function () {
    $this->getJson('/api/v1/children')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'يجب تسجيل الدخول أولاً.', 'code' => 'unauthenticated']);
});

it('answers 422 with Arabic messages and per-field errors', function () {
    $this->postJson('/api/v1/auth/tokens', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('message', 'البيانات المرسلة غير صحيحة.')
        ->assertJsonPath('errors.login.0', 'حقل اسم الدخول مطلوب.');
});

it('switches to English with Accept-Language', function () {
    $this->withHeader('Accept-Language', 'en')->postJson('/api/v1/auth/tokens', [])
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonPath('errors.login.0', 'The login field is required.');
});

it('never leaks model class names on 404', function () {
    [, $owner] = createNurseryWithOwner();

    $response = $this->actingAs($owner)->getJson('/api/v1/children/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'العنصر المطلوب غير موجود.', 'code' => 'not_found']);

    expect($response->getContent())->not->toContain('App\\Models');
});

it('distinguishes an unknown endpoint and a wrong method', function () {
    $this->getJson('/api/v1/nope')->assertNotFound()->assertJsonPath('message', 'المسار المطلوب غير موجود.');
    $this->deleteJson('/api/v1/auth/tokens')->assertStatus(405)->assertJsonPath('code', 'method_not_allowed');
});

it('answers 403 with a localized message', function () {
    [$tenant] = createNurseryWithOwner();
    $teacher = attachTeacher($tenant);
    $child = Child::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($teacher)->patchJson("/api/v1/children/{$child->id}", ['first_name' => 'x'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'غير مصرح لك بتنفيذ هذا الإجراء.', 'code' => 'forbidden']);
});

it('answers 402 when the plan lacks a feature', function () {
    Route::middleware(['api', 'auth:sanctum', 'tenant', 'entitled:auto_collection'])->get('/api/_test/paid', fn () => 'ok');
    [, $owner] = createNurseryWithOwner();

    $this->actingAs($owner)->getJson('/api/_test/paid')
        ->assertStatus(402)
        ->assertJsonPath('code', 'plan_upgrade_required');
});

it('answers 429 with Retry-After when throttled', function () {
    foreach (range(1, 6) as $i) {
        $this->postJson('/api/v1/auth/tokens', ['login' => 'a@b.c', 'password' => 'x', 'device_name' => 'd']);
    }

    $this->postJson('/api/v1/auth/tokens', ['login' => 'a@b.c', 'password' => 'x', 'device_name' => 'd'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');
});

it('hides internals on 500 outside debug mode', function () {
    config(['app.debug' => false]);
    Route::middleware('api')->get('/api/_test/boom', fn () => throw new RuntimeException('SQL secret details'));

    $this->getJson('/api/_test/boom')
        ->assertStatus(500)
        ->assertExactJson(['message' => 'حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.', 'code' => 'server_error']);
});
