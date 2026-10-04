<?php

namespace App\Services\Pickup;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The guardian's rotating pickup QR: a short-lived, HMAC-signed token bound to
 * one guardian in one nursery. A screenshot stops working after TTL seconds;
 * the app fetches a fresh token every REFRESH seconds.
 *
 * Format: BHG1.<base64url(json {g, t, e})>.<base64url(hmac)>
 */
class PickupTokenService
{
    public const TTL_SECONDS = 60;

    public const REFRESH_SECONDS = 30;

    private const PREFIX = 'BHG1';

    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     */
    public function issue(User $guardian, Tenant $tenant): array
    {
        $expiresAt = CarbonImmutable::now()->addSeconds(self::TTL_SECONDS);
        $payload = $this->encode(json_encode(['g' => $guardian->id, 't' => $tenant->id, 'e' => $expiresAt->getTimestamp()]));

        return [
            'token' => self::PREFIX.'.'.$payload.'.'.$this->sign($payload),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * The guardian id a valid, unexpired token for this nursery was issued to.
     */
    public function guardianId(string $token, Tenant $tenant): ?int
    {
        $parts = explode('.', trim($token));

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX || ! hash_equals($this->sign($parts[1]), $parts[2])) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);

        if (! is_array($data) || ($data['t'] ?? null) !== $tenant->id || ($data['e'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return is_int($data['g'] ?? null) ? $data['g'] : null;
    }

    private function sign(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', $payload, 'pickup|'.config('app.key'), true));
    }

    private function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
