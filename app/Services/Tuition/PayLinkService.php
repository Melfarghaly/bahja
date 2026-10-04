<?php

namespace App\Services\Tuition;

use App\Models\TuitionInvoice;
use Illuminate\Support\Facades\URL;

/**
 * Signed, expiring links that let a parent pay an invoice from an SMS without
 * signing in. The signature covers the nursery and the invoice.
 */
class PayLinkService
{
    public const VALID_DAYS = 30;

    public function linkFor(TuitionInvoice $invoice): string
    {
        return URL::temporarySignedRoute('pay.show', now()->addDays(self::VALID_DAYS), [
            'tenant' => $invoice->tenant_id,
            'invoice' => $invoice->id,
        ]);
    }

    public function checkoutActionFor(TuitionInvoice $invoice, string $gateway): string
    {
        return URL::temporarySignedRoute('pay.checkout', now()->addHours(2), [
            'tenant' => $invoice->tenant_id,
            'invoice' => $invoice->id,
            'gateway' => $gateway,
        ]);
    }
}
