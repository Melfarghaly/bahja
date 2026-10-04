<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentGatewayName;
use App\Enums\TuitionInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StartCheckoutRequest;
use App\Http\Resources\PaymentIntentResource;
use App\Http\Resources\TuitionInvoiceResource;
use App\Models\TuitionInvoice;
use App\Services\Tuition\OnlinePaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bahga Pay for parents: the invoices addressed to the signed-in guardian in
 * the current nursery. A guardian only ever sees invoices where they are the
 * payer — co-guardians without the payer flag see none.
 */
class MyInvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = TuitionInvoice::query()
            ->where('payer_id', $request->user()->id)
            ->where('status', '!=', TuitionInvoiceStatus::Void->value)
            ->latest('period_start')
            ->cursorPaginate(12);

        return TuitionInvoiceResource::collection($invoices);
    }

    public function show(Request $request, TuitionInvoice $invoice): TuitionInvoiceResource
    {
        abort_unless($invoice->payer_id === $request->user()->id, 404);

        return new TuitionInvoiceResource($invoice->load(['items', 'payments']));
    }

    public function checkout(StartCheckoutRequest $request, TuitionInvoice $invoice, OnlinePaymentService $online): PaymentIntentResource
    {
        abort_unless($invoice->payer_id === $request->user()->id, 404);

        $intent = $online->startCheckout($invoice, $request->user(), PaymentGatewayName::from($request->validated('gateway')));

        return new PaymentIntentResource($intent);
    }
}
