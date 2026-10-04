<?php

namespace App\Http\Resources;

use App\Models\Child;
use App\Services\AttendanceSheetService;
use App\Services\Pickup\PickupVerificationService;
use App\Support\Pickup\PickupCandidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The teacher's hand-over screen: who is at the door, and for each of the
 * children they could be here for — present today? allowed? why not?
 */
class PickupCheckResource extends JsonResource
{
    /**
     * @param  Collection<int, Child>  $children
     */
    public function __construct(PickupCandidate $candidate, private Collection $children, private PickupVerificationService $verification)
    {
        parent::__construct($candidate);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PickupCandidate $candidate */
        $candidate = $this->resource;

        return [
            'collector' => [
                'method' => $candidate->method->value,
                'user_id' => $candidate->user?->id,
                'pickup_pass_id' => $candidate->pass?->id,
                'name' => $candidate->name,
                'phone' => $candidate->phone,
                'pass_valid_until' => $candidate->pass?->valid_until->toIso8601String(),
            ],
            'children' => $this->children->map(function (Child $child) use ($candidate) {
                $refusal = $this->verification->refusal($candidate, $child);
                $attendance = $child->attendances->first();

                return [
                    'child' => [
                        'id' => $child->id,
                        'first_name' => $child->first_name,
                        'last_name' => $child->last_name,
                        'classroom' => $child->classroom ? ['id' => $child->classroom->id, 'name' => $child->classroom->name] : null,
                    ],
                    'attendance_status' => match (true) {
                        $attendance?->checked_out_at !== null => AttendanceSheetService::PICKED_UP,
                        $attendance?->checked_in_at !== null => AttendanceSheetService::PRESENT,
                        default => AttendanceSheetService::ABSENT,
                    },
                    'allowed' => $refusal === null,
                    'reason' => $refusal,
                    'reason_label' => $refusal ? __('pickup.reasons.'.$refusal) : null,
                ];
            })->values(),
        ];
    }
}
