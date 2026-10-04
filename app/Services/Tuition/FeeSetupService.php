<?php

namespace App\Services\Tuition;

use App\Enums\AuditAction;
use App\Enums\DiscountType;
use App\Enums\DiscountValueType;
use App\Models\FeeDiscount;
use App\Models\FeePlan;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Configures a nursery's fee plans and discounts. Amounts arrive as pound
 * strings from the forms and are stored as exact piasters / basis points.
 */
class FeeSetupService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function savePlan(array $data, ?FeePlan $plan = null): FeePlan
    {
        return DB::transaction(function () use ($data, $plan) {
            $attributes = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'amount_piasters' => Money::fromPounds($data['amount'])->piasters,
                'frequency' => $data['frequency'],
                'classroom_id' => $data['classroom_id'] ?? null,
            ];

            $plan === null
                ? $plan = FeePlan::create($attributes + ['is_active' => true])
                : $plan->update($attributes);

            $this->audit->record(AuditAction::FeePlanSaved, $plan, $attributes);

            return $plan;
        });
    }

    public function setPlanActive(FeePlan $plan, bool $active): FeePlan
    {
        $plan->update(['is_active' => $active]);

        $this->audit->record(AuditAction::FeePlanSaved, $plan, ['is_active' => $active]);

        return $plan;
    }

    /**
     * Only one sibling discount is active at a time: saving an active one
     * retires the previous rule.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDiscount(array $data, ?FeeDiscount $discount = null): FeeDiscount
    {
        return DB::transaction(function () use ($data, $discount) {
            $valueType = DiscountValueType::from($data['value_type']);

            $attributes = [
                'name' => $data['name'],
                'type' => $data['type'],
                'value_type' => $valueType,
                // "12.5" % → 1250 basis points; "100" EGP → 10000 piasters.
                'value' => Money::fromPounds($data['value'])->piasters,
            ];

            $discount === null
                ? $discount = FeeDiscount::create($attributes + ['is_active' => true])
                : $discount->update($attributes);

            if ($discount->type === DiscountType::Sibling && $discount->is_active) {
                FeeDiscount::where('type', DiscountType::Sibling)
                    ->whereKeyNot($discount->id)
                    ->update(['is_active' => false]);
            }

            $this->audit->record(AuditAction::FeeDiscountSaved, $discount, [
                ...$attributes,
                'value_type' => $valueType->value,
            ]);

            return $discount;
        });
    }

    public function setDiscountActive(FeeDiscount $discount, bool $active): FeeDiscount
    {
        return DB::transaction(function () use ($discount, $active) {
            if ($active && $discount->type === DiscountType::Sibling) {
                FeeDiscount::where('type', DiscountType::Sibling)->whereKeyNot($discount->id)->update(['is_active' => false]);
            }

            $discount->update(['is_active' => $active]);

            $this->audit->record(AuditAction::FeeDiscountSaved, $discount, ['is_active' => $active]);

            return $discount;
        });
    }
}
