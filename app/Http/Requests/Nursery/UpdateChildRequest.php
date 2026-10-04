<?php

namespace App\Http\Requests\Nursery;

use App\Enums\ChildStatus;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateChildRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', new Enum(Gender::class)],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'status' => ['required', new Enum(ChildStatus::class)],
        ];
    }
}
