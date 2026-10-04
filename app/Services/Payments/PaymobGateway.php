<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Paymob adapter. Network/SDK integration is intentionally left as a stub for
 * Phase 1 wiring; the contract and call sites are production-shaped.
 */
class PaymobGateway implements PaymentGateway
{
    public function charge(Invoice $invoice): PaymentResult
    {
        // TODO: call Paymob intention/charge API with $invoice->amount_egp.
        return new PaymentResult(
            successful: false,
            reference: 'pending',
            message: 'Paymob integration not yet wired.',
        );
    }

    public function handleWebhook(Request $request): void
    {
        // TODO: verify HMAC signature, then mark the related invoice/payment paid.
    }
}
