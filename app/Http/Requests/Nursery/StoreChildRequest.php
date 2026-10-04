<?php

namespace App\Http\Requests\Nursery;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\GuardianRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by tenant + nursery.staff route middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', new Enum(Gender::class)],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],

            'guardians' => ['required', 'array', 'min:1'],
            'guardians.*.name' => ['required', 'string', 'max:120'],
            'guardians.*.phone' => ['required', 'string', 'max:20'],
            'guardians.*.relationship' => ['required', new Enum(GuardianRelationship::class)],
            'guardians.*.role' => ['required', new Enum(GuardianRole::class)],
            'guardians.*.can_view_wall' => ['boolean'],
            'guardians.*.can_pickup' => ['boolean'],
            'guardians.*.is_payer' => ['boolean'],
        ];
    }
}
