<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $planId = $this->route('plan')->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('subscription_plans', 'slug')->ignore($planId)],
            'price_egp' => ['required', 'integer', 'min:0'],
            'billing_cycle' => ['required', new Enum(BillingCycle::class)],
            'max_children' => ['nullable', 'integer', 'min:1'],
            'max_teachers' => ['nullable', 'integer', 'min:1'],
            'included_sms' => ['required', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'is_active' => ['boolean'],
        ];
    }
}
