<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Contract every payment gateway (Paymob, Fawry, ...) must implement so the
 * billing service can stay gateway-agnostic.
 */
interface PaymentGateway
{
    public function charge(Invoice $invoice): PaymentResult;

    public function handleWebhook(Request $request): void;
}
