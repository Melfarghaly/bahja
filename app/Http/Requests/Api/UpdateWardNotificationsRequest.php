<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWardNotificationsRequest extends FormRequest
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
            // SMS for this child (payment reminders, fallback when no app is reachable).
            'sms' => ['required_without:push', 'boolean'],
            // App notifications for this child (arrived, picked up, ...).
            'push' => ['required_without:sms', 'boolean'],
        ];
    }
}
