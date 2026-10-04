<?php

namespace App\Http\Requests\Nursery;

use App\Enums\EmploymentType;
use App\Enums\TeacherRole;
use App\Enums\TeacherStatus;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTeacherRequest extends FormRequest
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
            'role' => ['required', new Enum(TeacherRole::class)],
            'status' => ['required', new Enum(TeacherStatus::class)],
            'employment_type' => ['required', new Enum(EmploymentType::class)],
            'classroom_id' => ['nullable', 'integer', TenantRules::exists('classrooms')],
        ];
    }
}
