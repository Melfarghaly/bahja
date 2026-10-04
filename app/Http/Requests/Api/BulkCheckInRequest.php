<?php

namespace App\Http\Requests\Api;

use App\Enums\AttendanceMethod;
use App\Models\Attendance;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class BulkCheckInRequest extends FormRequest
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
            'method' => ['nullable', new Enum(AttendanceMethod::class)],
            'children' => ['required', 'array', 'min:1', 'max:300'],
            'children.*.child_id' => ['required', 'integer', 'distinct', TenantRules::exists('children')],
            // When the device recorded it (offline sync). Today only, never in the future.
            'children.*.checked_in_at' => ['nullable', 'date', 'before_or_equal:now', 'after_or_equal:today'],
        ];
    }
}
