<?php

namespace App\Http\Requests;

use App\Enums\ChildStatus;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('child')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'birth_date' => ['sometimes', 'date', 'before:today'],
            'gender' => ['sometimes', new Enum(Gender::class)],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'medical_notes' => ['nullable', 'array'],
            'status' => ['sometimes', new Enum(ChildStatus::class)],
        ];
    }
}
