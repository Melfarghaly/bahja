<?php

namespace App\Http\Requests\Nursery\Finance;

use App\Enums\DiscountType;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class AssignFeePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fee_plan_id' => ['required', 'integer', TenantRules::exists('fee_plans')->where('is_active', true)],
            // Sibling discounts are automatic; only explicit discounts can be pinned to a child.
            'fee_discount_id' => [
                'nullable', 'integer',
                TenantRules::exists('fee_discounts')->where('is_active', true)->whereNot('type', DiscountType::Sibling->value),
            ],
            'starts_on' => ['required', 'date'],
        ];
    }
}
