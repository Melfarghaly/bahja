<?php

namespace App\Http\Requests;

use App\Enums\AttendanceMethod;
use App\Models\Attendance;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record', Attendance::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'child_id' => ['required', 'integer', TenantRules::exists('children')],
            'method' => ['nullable', new Enum(AttendanceMethod::class)],
        ];
    }
}
