<?php

namespace App\Http\Requests\Nursery;

use Illuminate\Foundation\Http\FormRequest;

class ImportChildrenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by tenant + nursery.staff route middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'يرجى اختيار ملف.',
            'file.mimes' => 'الملف يجب أن يكون بصيغة Excel (xlsx) أو CSV.',
            'file.max' => 'حجم الملف يتجاوز الحد المسموح (5 ميجابايت).',
        ];
    }
}
