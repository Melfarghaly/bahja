<?php

namespace App\Http\Requests\Nursery;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassroomRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
