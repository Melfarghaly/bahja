<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponDuration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'alpha_dash', 'max:40', Rule::unique('coupons', 'code')],
            'name' => ['required', 'string', 'max:120'],
            'percent_off' => ['required', 'integer', 'between:1,100'],
            'duration' => ['required', new Enum(CouponDuration::class)],
            'duration_months' => ['nullable', 'required_if:duration,'.CouponDuration::Repeating->value, 'integer', 'between:1,36'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'plan_slugs' => ['nullable', 'array'],
            'plan_slugs.*' => ['string', Rule::exists('subscription_plans', 'slug')],
            'valid_until' => ['nullable', 'date', 'after:now'],
        ];
    }
}
