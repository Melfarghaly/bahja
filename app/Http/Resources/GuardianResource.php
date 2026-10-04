<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * Represents a guardian in the context of a specific child, exposing the
 * per-pair permissions carried on the child_guardian pivot.
 */
class GuardianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'relationship' => $this->whenPivotLoaded('child_guardian', fn () => $this->pivot->relationship),
            'role' => $this->whenPivotLoaded('child_guardian', fn () => $this->pivot->role),
            'can_view_wall' => $this->whenPivotLoaded('child_guardian', fn () => (bool) $this->pivot->can_view_wall),
            'can_pickup' => $this->whenPivotLoaded('child_guardian', fn () => (bool) $this->pivot->can_pickup),
            'is_payer' => $this->whenPivotLoaded('child_guardian', fn () => (bool) $this->pivot->is_payer),
            // Staff must see a custody block to refuse a pickup.
            'custody_flag' => $this->whenPivotLoaded('child_guardian', fn () => $this->pivot->custody_flag),
            'billing_share_bp' => $this->whenPivotLoaded('child_guardian', fn () => $this->pivot->billing_share_bp === null ? null : (int) $this->pivot->billing_share_bp),
        ];
    }
}
