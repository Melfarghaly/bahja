<?php

namespace App\Services\Notifications\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 API: Android, iOS and web from one token
 * format. Authenticates as a service account (a signed JWT exchanged for an
 * OAuth access token, cached until shortly before it expires).
 */
class FcmPushGateway implements PushGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param  array{project_id: string, client_email: string, private_key: string, token_uri?: string}  $credentials
     */
    public function __construct(private array $credentials) {}

    public static function fromConfig(): self
    {
        $raw = (string) config('services.push.fcm.credentials');
        $json = is_file($raw) ? (string) file_get_contents($raw) : $raw;
        $credentials = json_decode($json, true);

        if (! is_array($credentials) || ! isset($credentials['project_id'], $credentials['client_email'], $credentials['private_key'])) {
            throw new RuntimeException('FCM credentials are missing or invalid (services.push.fcm.credentials).');
        }

        return new self($credentials);
    }

    public function send(string $deviceToken, PushMessage $message): PushResult
    {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->credentials['project_id']}/messages:send";

        try {
            $response = Http::withToken($this->accessToken())
                ->timeout(10)
                ->post($url, ['message' => $this->payload($deviceToken, $message)]);
        } catch (ConnectionException $e) {
            return new PushResult(false, error: 'connection: '.$e->getMessage());
        }

        if ($response->successful()) {
            return new PushResult(true, (string) $response->json('name'));
        }

        $code = collect($response->json('error.details', []))->pluck('errorCode')->filter()->first()
            ?? $response->json('error.status', 'HTTP_'.$response->status());

        $error = (string) $response->json('error.message', '');

        return new PushResult(
            false,
            error: mb_substr($code.': '.$error, 0, 500),
            // The app was uninstalled or the token is malformed. Other
            // INVALID_ARGUMENT errors are our payload's fault: keep the device.
            invalidToken: $code === 'UNREGISTERED'
                || ($code === 'INVALID_ARGUMENT' && str_contains(strtolower($error), 'registration token')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $deviceToken, PushMessage $message): array
    {
        $payload = [
            'token' => $deviceToken,
            'notification' => ['title' => $message->title, 'body' => $message->body],
            'android' => ['priority' => $message->urgent ? 'HIGH' : 'NORMAL', 'notification' => ['sound' => 'default']],
            'apns' => [
                'headers' => ['apns-priority' => $message->urgent ? '10' : '5'],
                'payload' => ['aps' => ['sound' => 'default']],
            ],
        ];

        if ($message->data !== []) {
            $payload['data'] = array_map('strval', $message->data);
        }

        return $payload;
    }

    private function accessToken(): string
    {
        $key = 'fcm-access-token|'.$this->credentials['client_email'];

        return Cache::remember($key, now()->addMinutes(50), function () {
            $response = Http::asForm()->timeout(10)->post($this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion(),
            ]);

            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('FCM authentication failed: HTTP '.$response->status());
            }

            return $response->json('access_token');
        });
    }

    private function assertion(): string
    {
        $now = time();
        $encode = fn (array $part) => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'iss' => $this->credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        if (! openssl_sign($unsigned, $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('FCM: could not sign the service account assertion.');
        }

        return $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
