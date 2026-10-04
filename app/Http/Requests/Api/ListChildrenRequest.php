<?php

namespace App\Http\Requests\Api;

use App\Enums\ChildStatus;
use App\Models\Child;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListChildrenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Child::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'classroom_id' => ['nullable', 'integer', TenantRules::exists('classrooms')],
            'status' => ['nullable', new Enum(ChildStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
