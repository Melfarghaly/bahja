<?php

namespace App\Services;

use App\Models\User;
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
        $user = User::query()
            ->where(str_contains($login, '@') ? 'email' : 'phone', $login)
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        $user->tokens()->where('name', $deviceName)->delete();

        return $user->createToken($deviceName)->plainTextToken;
    }

    public function revoke(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
