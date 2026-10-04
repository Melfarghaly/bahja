<?php

use App\Services\Notifications\Push\FcmPushGateway;
use App\Services\Notifications\Push\PushMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Cache::flush();
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $this->publicKey = openssl_pkey_get_details($key)['key'];
    $this->gateway = new FcmPushGateway(['project_id' => 'bahga-app', 'client_email' => 'push@bahga-app.iam.gserviceaccount.com', 'private_key' => $pem]);
});

it('authenticates with a signed service-account assertion and sends an FCM v1 message', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/bahga-app/messages/0:1']),
    ]);

    $result = $this->gateway->send('device-token-1', new PushMessage('وصل يوسف', 'وصل يوسف الحضانة', ['child_id' => '7'], urgent: true));
    $this->gateway->send('device-token-2', new PushMessage('t', 'b'));

    expect($result->successful)->toBeTrue()->and($result->providerReference)->toBe('projects/bahga-app/messages/0:1');

    Http::assertSentCount(3);   // one token exchange, cached for both sends
    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), 'oauth2')) {
            return false;
        }
        [$header, $claims, $signature] = explode('.', $request['assertion']);
        $decoded = json_decode(base64_decode(strtr($claims, '-_', '+/')), true);
        $valid = openssl_verify("{$header}.{$claims}", base64_decode(strtr($signature, '-_', '+/')), $this->publicKey, OPENSSL_ALGO_SHA256) === 1;

        return $valid && $decoded['iss'] === 'push@bahga-app.iam.gserviceaccount.com'
            && $decoded['scope'] === 'https://www.googleapis.com/auth/firebase.messaging';
    });
    Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/bahga-app/messages:send'
        && $request->hasHeader('Authorization', 'Bearer ya29.test')
        && $request['message']['token'] === 'device-token-1'
        && $request['message']['notification'] === ['title' => 'وصل يوسف', 'body' => 'وصل يوسف الحضانة']
        && $request['message']['data'] === ['child_id' => '7']
        && $request['message']['android']['priority'] === 'HIGH');
});

it('reports an uninstalled app so the device is forgotten, but not a payload error', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test']),
        'fcm.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['code' => 404, 'message' => 'Requested entity was not found.', 'status' => 'NOT_FOUND', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404)
            ->push(['error' => ['code' => 400, 'message' => 'Invalid JSON payload received.', 'status' => 'INVALID_ARGUMENT', 'details' => [['errorCode' => 'INVALID_ARGUMENT']]]], 400),
    ]);

    $gone = $this->gateway->send('old-token', new PushMessage('t', 'b'));
    $bad = $this->gateway->send('good-token', new PushMessage('t', 'b'));

    expect($gone->successful)->toBeFalse()->and($gone->invalidToken)->toBeTrue()->and($gone->error)->toStartWith('UNREGISTERED')
        ->and($bad->successful)->toBeFalse()->and($bad->invalidToken)->toBeFalse();
});

it('refuses to start without valid credentials', function () {
    config(['services.push.fcm.credentials' => '{"project_id":"x"}']);

    expect(fn () => FcmPushGateway::fromConfig())->toThrow(RuntimeException::class, 'FCM credentials');
});
