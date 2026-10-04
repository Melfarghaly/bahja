<?php

namespace App\Http\Requests\Api;

use App\Rules\EgyptianMobile;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:20', new EgyptianMobile],
            'code' => ['required', 'string', 'digits:6'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
