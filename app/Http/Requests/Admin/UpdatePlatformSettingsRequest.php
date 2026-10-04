<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hex = ['required', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'];

        return [
            'brand_name' => ['required', 'string', 'max:100'],
            'primary_color' => $hex,
            'secondary_color' => $hex,
            'accent_color' => $hex,
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ];
    }
}
