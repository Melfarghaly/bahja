<?php

namespace App\Http\Resources;

use App\Models\Child;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A child as seen by one of their guardians: never the other guardians'
 * details. Medical notes only for guardians allowed to view the child's wall.
 *
 * @mixin Child
 */
class WardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $link = $this->pivot;
        $canViewWall = (bool) $link->can_view_wall;
        $preferences = json_decode((string) $link->notify_preferences, true) ?: [];
        $today = $this->relationLoaded('attendances') ? $this->attendances->first() : null;

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender' => $this->gender->value,
            'status' => $this->status->value,
            'classroom' => $this->whenLoaded('classroom', fn () => $this->classroom ? ['id' => $this->classroom->id, 'name' => $this->classroom->name] : null),
            'medical_notes' => $this->when($canViewWall, $this->medical_notes),
            'my_link' => [
                'relationship' => $link->relationship,
                'role' => $link->role,
                'can_view_wall' => $canViewWall,
                'can_pickup' => (bool) $link->can_pickup,
                'is_payer' => (bool) $link->is_payer,
                'billing_share_bp' => $link->billing_share_bp === null ? null : (int) $link->billing_share_bp,
                'notifications' => [
                    'push' => ($preferences['push'] ?? true) !== false,
                    'sms' => ($preferences['sms'] ?? true) !== false,
                ],
            ],
            'today_attendance' => $this->when($this->relationLoaded('attendances'), fn () => $today ? new AttendanceResource($today) : null),
        ];
    }
}
