<?php

namespace App\Http\Resources;

use App\Models\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentIntent
 */
class PaymentIntentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gateway' => $this->gateway->value,
            'status' => $this->status->value,
            'amount' => $this->amount(),
            'checkout_url' => $this->checkout_url,
            'payment_code' => $this->payment_code,
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
