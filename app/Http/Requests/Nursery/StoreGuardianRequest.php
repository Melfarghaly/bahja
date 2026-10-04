<?php

namespace App\Http\Requests\Nursery;

use App\Enums\GuardianRelationship;
use App\Enums\GuardianRole;
use App\Rules\PoundAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreGuardianRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'relationship' => ['required', new Enum(GuardianRelationship::class)],
            'role' => ['required', new Enum(GuardianRole::class)],
            'can_view_wall' => ['boolean'],
            'can_pickup' => ['boolean'],
            'is_payer' => ['boolean'],
            // Share of the child's fees this payer covers, in percent (e.g. 60 or 33.33).
            'billing_share_percent' => ['nullable', new PoundAmount(maxPounds: 100)],
        ];
    }
}
