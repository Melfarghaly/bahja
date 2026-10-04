<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserProfileResource extends JsonResource
{
    /**
     * @param  array<int, array<string, mixed>>  $nurseries
     */
    public function __construct(User $user, private array $nurseries)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'locale' => app()->getLocale(),
            // Send the chosen nursery's id as the X-Tenant-Id header on every call.
            'nurseries' => $this->nurseries,
        ];
    }
}
