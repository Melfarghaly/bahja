<?php

namespace App\Http\Requests\Api;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPickupRequest extends FormRequest
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
            // The scanned guardian QR, or a pass code typed by the teacher.
            'pickup_token' => ['nullable', 'string', 'max:512', 'required_without:pass_code', 'prohibits:pass_code'],
            'pass_code' => ['nullable', 'string', 'digits:6'],
        ];
    }
}
