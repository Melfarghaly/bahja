<?php

namespace App\Http\Requests\Nursery;

use App\Enums\GuardianRelationship;
use App\Enums\GuardianRole;
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
        ];
    }
}
