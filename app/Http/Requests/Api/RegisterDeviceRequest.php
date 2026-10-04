<?php

namespace App\Http\Requests\Api;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
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
            // The FCM registration token from the app.
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'locale' => ['nullable', Rule::in(['ar', 'en'])],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
