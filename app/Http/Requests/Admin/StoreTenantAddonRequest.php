<?php

namespace App\Http\Requests\Admin;

use App\Enums\Addon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTenantAddonRequest extends FormRequest
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
        return [
            'addon' => ['required', new Enum(Addon::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
