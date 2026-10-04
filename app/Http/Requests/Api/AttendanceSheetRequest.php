<?php

namespace App\Http\Requests\Api;

use App\Models\Attendance;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceSheetRequest extends FormRequest
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
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'classroom_id' => ['nullable', 'integer', TenantRules::exists('classrooms')],
        ];
    }
}
