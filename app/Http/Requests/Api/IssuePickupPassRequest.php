<?php

namespace App\Http\Requests\Api;

use App\Rules\EgyptianMobile;
use App\Services\Pickup\PickupPassService;
use Illuminate\Foundation\Http\FormRequest;

class IssuePickupPassRequest extends FormRequest
{
    /**
     * Pickup rights on this child are checked by the controller (403).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $from = $this->input('valid_from') ?: 'now';

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new EgyptianMobile],
            'valid_from' => ['nullable', 'date', 'after_or_equal:today'],
            'valid_until' => [
                'required', 'date', 'after:now', 'after:'.$from,
                'before_or_equal:'.now()->parse($from)->addHours(PickupPassService::MAX_HOURS)->toDateTimeString(),
            ],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['valid_until.before_or_equal' => __('pickup.pass_too_long', ['hours' => PickupPassService::MAX_HOURS])];
    }
}
