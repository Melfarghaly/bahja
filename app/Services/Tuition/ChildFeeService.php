<?php

namespace App\Services\Tuition;

use App\Enums\AuditAction;
use App\Models\Child;
use App\Models\ChildFeePlan;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrolls a child in fee plans and ends those enrollments. History is kept:
 * an enrollment is closed with an end date, never deleted.
 */
class ChildFeeService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{fee_plan_id: int|string, fee_discount_id?: int|string|null, starts_on: string}  $data
     *
     * @throws ValidationException when the child already has this plan open.
     */
    public function assign(Child $child, array $data): ChildFeePlan
    {
        // Billing is monthly: an enrollment always starts on the 1st.
        $startsOn = CarbonImmutable::parse($data['starts_on'])->startOfMonth();

        return DB::transaction(function () use ($child, $data, $startsOn) {
            $alreadyOpen = $child->feePlans()
                ->where('fee_plan_id', $data['fee_plan_id'])
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $startsOn->toDateString()))
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages([
                    'fee_plan_id' => 'الطفل مسجّل بالفعل في هذه الرسوم. أنهِ التسجيل الحالي أولاً.',
                ]);
            }

            $assignment = $child->feePlans()->create([
                'tenant_id' => $child->tenant_id,
                'fee_plan_id' => $data['fee_plan_id'],
                'fee_discount_id' => $data['fee_discount_id'] ?? null,
                'starts_on' => $startsOn,
            ]);

            $this->audit->record(AuditAction::FeeAssigned, $child, [
                'child_fee_plan_id' => $assignment->id,
                'fee_plan_id' => (int) $data['fee_plan_id'],
                'fee_discount_id' => isset($data['fee_discount_id']) ? (int) $data['fee_discount_id'] : null,
                'starts_on' => $assignment->starts_on->toDateString(),
            ]);

            return $assignment;
        });
    }

    /**
     * Stop billing a plan after the month of $endsOn.
     */
    public function end(ChildFeePlan $assignment, string $endsOn): ChildFeePlan
    {
        return DB::transaction(function () use ($assignment, $endsOn) {
            $assignment->update(['ends_on' => CarbonImmutable::parse($endsOn)->endOfMonth()]);

            $this->audit->record(AuditAction::FeeAssignmentEnded, $assignment->child, [
                'child_fee_plan_id' => $assignment->id,
                'ends_on' => $assignment->ends_on->toDateString(),
            ]);

            return $assignment;
        });
    }
}
