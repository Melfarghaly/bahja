<?php

namespace App\Http\Requests;

use App\Enums\CustodyFlag;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\GuardianRole;
use App\Models\Child;
use App\Rules\PoundAmount;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Child::class) ?? false;
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
            'classroom_id' => ['nullable', 'integer', TenantRules::exists('classrooms')],
            'medical_notes' => ['nullable', 'array'],

            'guardians' => ['required', 'array', 'min:1'],
            // An existing Bahga user, or a name + phone (the account is found or created).
            'guardians.*.user_id' => ['required_without:guardians.*.phone', 'nullable', 'integer', 'exists:users,id'],
            'guardians.*.phone' => ['required_without:guardians.*.user_id', 'nullable', 'string', 'max:20'],
            'guardians.*.name' => ['required_with:guardians.*.phone', 'nullable', 'string', 'max:120'],
            'guardians.*.billing_share_percent' => ['nullable', new PoundAmount(maxPounds: 100)],
            'guardians.*.relationship' => ['required', new Enum(GuardianRelationship::class)],
            'guardians.*.role' => ['required', new Enum(GuardianRole::class)],
            'guardians.*.can_view_wall' => ['boolean'],
            'guardians.*.can_pickup' => ['boolean'],
            'guardians.*.is_payer' => ['boolean'],
            'guardians.*.custody_flag' => ['nullable', new Enum(CustodyFlag::class)],
        ];
    }
}
