<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Check-out identifies the collector in exactly one way:
 *   - collector_id: a guardian picked from the authorized list;
 *   - pickup_token: the guardian's rotating QR, scanned;
 *   - pass_code: a one-time pass for someone without an account;
 *   - collector_name + override_reason: a manager's manual override.
 */
class CheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record', Attendance::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ways = ['collector_id', 'pickup_token', 'pass_code', 'override_reason'];
        $others = fn (string $field) => implode(',', array_diff($ways, [$field]));

        return [
            'child_id' => ['required', 'integer', TenantRules::exists('children')],
            'collector_id' => ['nullable', 'integer', 'exists:users,id', 'required_without_all:'.$others('collector_id'), 'prohibits:'.$others('collector_id')],
            'pickup_token' => ['nullable', 'string', 'max:512', 'prohibits:'.$others('pickup_token')],
            'pass_code' => ['nullable', 'string', 'digits:6', 'prohibits:'.$others('pass_code')],
            'override_reason' => ['nullable', 'string', 'min:5', 'max:200', 'prohibits:'.$others('override_reason')],
            'collector_name' => ['required_with:override_reason', 'nullable', 'string', 'max:120'],
        ];
    }

    public function isOverride(): bool
    {
        return filled($this->validated('override_reason'));
    }
}
