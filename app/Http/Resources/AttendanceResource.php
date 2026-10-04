<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'child_id' => $this->child_id,
            'date' => $this->date?->toDateString(),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'check_in_method' => $this->check_in_method?->value,
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'picked_up_by' => $this->picked_up_by,
            'picked_up_by_name' => $this->whenLoaded('pickedUpBy', fn () => $this->pickedUpBy?->name),
            'pickup_verified' => $this->pickup_verified,
        ];
    }
}
