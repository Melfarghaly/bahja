<?php

namespace App\Http\Requests\Nursery\Finance;

use App\Enums\DiscountType;
use App\Enums\DiscountValueType;
use App\Rules\PoundAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SaveFeeDiscountRequest extends FormRequest
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
        $isPercent = $this->input('value_type') === DiscountValueType::Percent->value;

        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', new Enum(DiscountType::class)],
            'value_type' => ['required', new Enum(DiscountValueType::class)],
            // Percent: 0.01–100 with 2 decimals. Fixed: a pound amount.
            'value' => $isPercent
                ? ['required', new PoundAmount(maxPounds: 100)]
                : ['required', new PoundAmount],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'اسم الخصم', 'value' => 'قيمة الخصم'];
    }
}
