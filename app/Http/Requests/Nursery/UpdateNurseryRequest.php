<?php

namespace App\Http\Requests\Nursery;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNurseryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            // Bahga Pay: day of the month tuition invoices fall due (1–28 so it exists every month).
            'tuition_due_day' => ['nullable', 'integer', 'between:1,28'],
            // Safe Pickup: latest pickup time (Cairo, HH:MM); empty disables late alerts.
            'pickup_deadline' => ['nullable', 'date_format:H:i'],
        ];
    }
}
