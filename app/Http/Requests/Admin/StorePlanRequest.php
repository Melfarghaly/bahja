<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingCycle;
use App\Enums\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePlanRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('subscription_plans', 'slug')],
            'price_egp' => ['required', 'integer', 'min:0'],
            'billing_cycle' => ['required', new Enum(BillingCycle::class)],
            'max_children' => ['nullable', 'integer', 'min:1'],
            'max_teachers' => ['nullable', 'integer', 'min:1'],
            'included_sms' => ['required', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => [new Enum(Feature::class)],
            'is_active' => ['boolean'],
        ];
    }
}
