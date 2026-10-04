<?php

namespace App\Http\Requests\Nursery;

use App\Enums\EmploymentType;
use App\Enums\TeacherRole;
use App\Enums\TeacherStatus;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTeacherRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', new Enum(TeacherRole::class)],
            'status' => ['nullable', new Enum(TeacherStatus::class)],
            'employment_type' => ['nullable', new Enum(EmploymentType::class)],
            'classroom_id' => ['nullable', 'integer', TenantRules::exists('classrooms')],
        ];
    }
}
