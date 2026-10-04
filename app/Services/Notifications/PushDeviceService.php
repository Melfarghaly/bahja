<?php

namespace App\Services\Notifications;

use App\Models\PushDevice;
use App\Models\User;

/**
 * App installs that receive push notifications. A token belongs to one user
 * at a time: signing into another account on the same phone moves it.
 */
class PushDeviceService
{
    /**
     * @param  array{token: string, platform: string, locale?: ?string, app_version?: ?string}  $data
     * @return array{device: PushDevice, created: bool}
     */
    public function register(User $user, ?int $accessTokenId, array $data): array
    {
        $device = PushDevice::updateOrCreate(['token' => $data['token']], [
            'user_id' => $user->id,
            'personal_access_token_id' => $accessTokenId,
            'platform' => $data['platform'],
            'locale' => $data['locale'] ?? 'ar',
            'app_version' => $data['app_version'] ?? null,
            'last_seen_at' => now(),
        ]);

        return ['device' => $device, 'created' => $device->wasRecentlyCreated];
    }

    public function forget(User $user, string $token): void
    {
        PushDevice::where('user_id', $user->id)->where('token', $token)->delete();
    }
}
