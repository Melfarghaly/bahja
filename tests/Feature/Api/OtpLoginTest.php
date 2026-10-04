<?php

use App\Models\LoginCode;
use App\Models\User;
use App\Services\Messaging\SmsGateway;
use Tests\Support\FakeSmsGateway;

beforeEach(function () {
    $this->sms = new FakeSmsGateway;
    $this->app->instance(SmsGateway::class, $this->sms);
    $this->parent = User::factory()->create(['phone' => '01012345678', 'phone_verified_at' => null]);
});

function sentCode($test): string
{
    preg_match('/\d{6}/', end($test->sms->sent)['message'], $m);

    return $m[0];
}

it('signs a parent in with an SMS code, whatever the phone format', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => '+20 101 234 5678'])
        ->assertStatus(202)
        ->assertJsonPath('expires_in', 300)
        ->assertJsonPath('resend_after', 60);

    expect($this->sms->sent[0]['phone'])->toBe('01012345678')
        ->and($this->sms->sent[0]['message'])->toContain('رمز الدخول إلى بهجة')
        ->and(LoginCode::sole()->code_hash)->not->toContain(sentCode($this));   // never stored in clear

    $token = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => sentCode($this), 'device_name' => 'mona-phone'])
        ->assertCreated()->json('token');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.phone', '01012345678');
    expect($this->parent->fresh()->phone_verified_at)->not->toBeNull();
});

it('answers the same for unknown numbers without sending anything', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => '01199999999'])
        ->assertStatus(202)->assertJsonPath('message', 'إن كان الرقم مسجلاً لدينا فسيصلك رمز الدخول خلال لحظات.');

    expect($this->sms->sent)->toBe([]);
});

it('rejects a wrong code and burns the code after 5 attempts', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => '01012345678']);
    $good = sentCode($this);

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => '000000', 'device_name' => 'x'])
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'رمز الدخول غير صحيح.');
    }

    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => $good, 'device_name' => 'x'])
        ->assertUnprocessable()->assertJsonPath('errors.code.0', 'رمز الدخول منتهي الصلاحية. اطلب رمزاً جديداً.');
});

it('expires codes after 5 minutes and accepts each code once', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => '01012345678']);
    $code = sentCode($this);

    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => $code, 'device_name' => 'a'])->assertCreated();
    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => $code, 'device_name' => 'b'])->assertUnprocessable();

    $this->travel(2)->minutes();
    $this->postJson('/api/v1/auth/otp', ['phone' => '01012345678']);
    $this->travel(6)->minutes();
    $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => sentCode($this), 'device_name' => 'c'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('enforces a resend cooldown and validates the phone', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => '01012345678'])->assertStatus(202);
    $this->postJson('/api/v1/auth/otp', ['phone' => '01012345678'])
        ->assertUnprocessable()->assertJsonValidationErrors('phone');

    $this->postJson('/api/v1/auth/otp', ['phone' => '12345'])
        ->assertUnprocessable()->assertJsonPath('errors.phone.0', 'رقم الهاتف يجب أن يكون رقم موبايل مصرياً صحيحاً.');

    expect($this->sms->sent)->toHaveCount(1);
});

it('lets a signed-in user update their name and email', function () {
    $this->actingAs($this->parent)->patchJson('/api/v1/me', ['name' => 'منى عبد الله', 'email' => 'mona@example.com'])
        ->assertOk()->assertJsonPath('data.name', 'منى عبد الله')->assertJsonPath('data.email', 'mona@example.com');

    $taken = User::factory()->create(['email' => 'taken@example.com']);
    $this->actingAs($this->parent)->patchJson('/api/v1/me', ['email' => $taken->email])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('accepts password login with any phone format too', function () {
    $this->parent->update(['password' => 'secret-pass']);

    $this->postJson('/api/v1/auth/tokens', ['login' => '+201012345678', 'password' => 'secret-pass', 'device_name' => 'x'])->assertCreated();
});

it('keeps password-login throttling separate from SMS codes', function () {
    foreach (range(1, 7) as $i) {
        $this->postJson('/api/v1/auth/tokens', ['login' => '01012345678', 'password' => 'wrong', 'device_name' => 'x']);
    }

    $this->postJson('/api/v1/auth/otp', ['phone' => '01012345678'])->assertStatus(202);
});
