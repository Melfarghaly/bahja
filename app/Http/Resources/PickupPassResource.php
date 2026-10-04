<?php

namespace App\Http\Resources;

use App\Models\PickupPass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PickupPass
 */
class PickupPassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'child_id' => $this->child_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'note' => $this->note,
            'status' => $this->status(),     // active / scheduled / used / expired / revoked
            'valid_from' => $this->valid_from->toIso8601String(),
            'valid_until' => $this->valid_until->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
        ];
    }
}
