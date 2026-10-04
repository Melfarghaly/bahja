<?php

namespace App\Services;

use App\Models\PushDevice;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues and revokes Sanctum personal access tokens for the PWA / mobile apps.
 * One token per device; re-issuing for the same device replaces the old one.
 */
class ApiTokenService
{
    /**
     * @throws ValidationException when the credentials do not match.
     */
    public function issue(string $login, string $password, string $deviceName): string
    {
        $user = str_contains($login, '@')
            ? User::where('email', $login)->first()
            : User::where('phone', PhoneNumber::normalize($login))->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        return $this->issueFor($user, $deviceName);
    }

    /**
     * One token per device: re-issuing for the same device replaces it.
     */
    public function issueFor(User $user, string $deviceName): string
    {
        $user->tokens()->where('name', $deviceName)->delete();

        return $user->createToken($deviceName)->plainTextToken;
    }

    public function revoke(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            // This install stops receiving notifications too (also enforced by the FK).
            PushDevice::where('personal_access_token_id', $token->id)->delete();
            $token->delete();
        }
    }
}
