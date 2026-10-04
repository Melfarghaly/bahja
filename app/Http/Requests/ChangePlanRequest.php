<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Subscription::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ];
    }
}
