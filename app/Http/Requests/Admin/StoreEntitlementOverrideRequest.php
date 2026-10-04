<?php

namespace App\Http\Requests\Admin;

use App\Enums\Feature;
use App\Enums\Limit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntitlementOverrideRequest extends FormRequest
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
        $keys = [
            ...array_map(fn (Feature $f) => $f->value, Feature::cases()),
            ...array_map(fn (Limit $l) => $l->value, Limit::cases()),
        ];

        return [
            'key' => ['required', Rule::in($keys)],
            // Features: on/off. Limits: a number, or empty for unlimited.
            'value' => Feature::tryFrom((string) $this->input('key')) !== null
                ? ['required', Rule::in(['on', 'off'])]
                : ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
