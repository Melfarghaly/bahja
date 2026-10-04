<?php

namespace App\Http\Controllers\Nursery\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nursery\Finance\RecordPaymentRequest;
use App\Http\Requests\Nursery\Finance\VoidRequest;
use App\Models\TuitionInvoice;
use App\Models\TuitionPayment;
use App\Services\Tuition\TuitionPaymentService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TuitionPaymentController extends Controller
{
    public function __construct(
        private TuitionPaymentService $payments,
        private TenantContext $tenantContext,
    ) {}

    public function store(RecordPaymentRequest $request, TuitionInvoice $invoice): RedirectResponse
    {
        $payment = $this->payments->record($invoice, $request->validated(), $request->user());

        return redirect()->route('nursery.finance.invoices.show', $invoice)
            ->with('status', "تم تسجيل {$payment->amount()->format()} — إيصال رقم {$payment->receipt_number}.")
            ->with('receipt', $payment->id);
    }

    public function void(VoidRequest $request, TuitionPayment $payment): RedirectResponse
    {
        $this->payments->voidPayment($payment, $request->validated('reason'), $request->user());

        return back()->with('status', "تم إلغاء الإيصال {$payment->receipt_number}.");
    }

    public function voidInvoice(VoidRequest $request, TuitionInvoice $invoice): RedirectResponse
    {
        $this->payments->voidInvoice($invoice, $request->validated('reason'), $request->user());

        return back()->with('status', "تم إلغاء الفاتورة {$invoice->number}.");
    }

    public function receipt(TuitionPayment $payment): View
    {
        $payment->load(['invoice.payer:id,name,phone', 'invoice.items.child:id,first_name,last_name', 'receivedBy:id,name']);

        return view('nursery.finance.receipt', [
            'payment' => $payment,
            'tenant' => $this->tenantContext->get(),
        ]);
    }
}
