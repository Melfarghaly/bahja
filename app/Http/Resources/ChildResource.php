<?php

namespace App\Http\Resources;

use App\Models\Child;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Child
 */
class ChildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender' => $this->gender->value,
            'status' => $this->status->value,
            'medical_notes' => $this->medical_notes,
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'guardians' => GuardianResource::collection($this->whenLoaded('guardians')),
        ];
    }
}
