<?php

namespace App\Http\Requests;

use App\Enums\CustodyFlag;
use App\Enums\GuardianRelationship;
use App\Enums\GuardianRole;
use App\Rules\PoundAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AttachGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('child')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'relationship' => ['required', new Enum(GuardianRelationship::class)],
            'role' => ['required', new Enum(GuardianRole::class)],
            'can_view_wall' => ['boolean'],
            'can_pickup' => ['boolean'],
            'is_payer' => ['boolean'],
            // Share of the child's fees this payer covers, in percent (e.g. 60 or 33.33).
            'billing_share_percent' => ['nullable', new PoundAmount(maxPounds: 100)],
            'custody_flag' => ['nullable', new Enum(CustodyFlag::class)],
        ];
    }
}
