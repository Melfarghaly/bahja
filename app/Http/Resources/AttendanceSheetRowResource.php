<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the daily attendance sheet: { child, status, attendance }.
 */
class AttendanceSheetRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $child = $this->resource['child'];

        return [
            'child' => [
                'id' => $child->id,
                'first_name' => $child->first_name,
                'last_name' => $child->last_name,
                'classroom' => $child->classroom ? ['id' => $child->classroom->id, 'name' => $child->classroom->name] : null,
            ],
            'status' => $this->resource['status'],
            'late_pickup' => $this->resource['late_pickup'],
            'attendance' => $this->resource['attendance'] ? new AttendanceResource($this->resource['attendance']) : null,
        ];
    }
}
