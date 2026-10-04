<?php

namespace App\Services;

use App\Models\User;

/**
 * Self-service profile edits from the apps (the phone stays the identity and
 * is changed only by the nursery).
 */
class ProfileUpdateService
{
    /**
     * @param  array{name?: string, email?: ?string}  $data
     */
    public function update(User $user, array $data): User
    {
        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $user->forceFill($data)->save();

        return $user;
    }
}
