<?php

namespace App\Services;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Resolves global user identities. Guardians and teachers are users that may
 * already exist (a parent with children in two nurseries, a substitute teacher),
 * so we look them up by phone before creating a new account.
 */
class UserDirectoryService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreateByPhone(string $phone, array $attributes = []): User
    {
        $phone = PhoneNumber::normalize($phone);

        $user = User::where('phone', $phone)->first();

        if ($user !== null) {
            return $user;
        }

        return User::create([
            'name' => $attributes['name'] ?? 'مستخدم',
            'phone' => $phone,
            'email' => $attributes['email'] ?? null,
            // A random password; the user sets a real one via phone-based onboarding later.
            'password' => Hash::make(Str::random(32)),
        ]);
    }
}
