<?php

namespace App\Http\Requests\Api;

use App\Enums\PaymentGatewayName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StartCheckoutRequest extends FormRequest
{
    /**
     * Payer ownership is checked by the controller (404 for anyone else).
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
        return [
            'gateway' => ['required', new Enum(PaymentGatewayName::class)],
        ];
    }
}
