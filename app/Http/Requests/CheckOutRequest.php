<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class CheckOutRequest extends FormRequest
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
            'collector_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
