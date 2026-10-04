<?php

namespace App\Http\Requests;

use App\Models\Attendance;
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
            'child_id' => ['required', 'integer', 'exists:children,id'],
            'collector_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
