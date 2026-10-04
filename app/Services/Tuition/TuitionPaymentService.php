<?php

namespace App\Services\Tuition;

use App\Enums\AuditAction;
use App\Enums\TuitionInvoiceStatus;
use App\Enums\TuitionPaymentMethod;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\TuitionPayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records money received against family invoices and corrects mistakes.
 * Every change locks the invoice row (no lost updates under concurrent
 * cashiers), keeps `paid_piasters` in step with the ledger, and is audited.
 * Nothing is deleted: payments and invoices are voided with a reason.
 */
class TuitionPaymentService
{
    public function __construct(
        private LedgerService $ledger,
        private DocumentNumberService $numbers,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{amount: string, method: string, reference?: ?string, paid_at?: ?string, notes?: ?string}  $data
     *
     * @throws ValidationException when the invoice cannot take this payment.
     */
    public function record(TuitionInvoice $invoice, array $data, User $cashier): TuitionPayment
    {
        $amount = Money::fromPounds($data['amount']);

        return DB::transaction(function () use ($invoice, $data, $amount, $cashier) {
            $invoice = $this->lock($invoice);

            if (! $invoice->status->isCollectible()) {
                throw ValidationException::withMessages(['amount' => 'لا يمكن تسجيل دفعة على فاتورة '.$invoice->status->label().'.']);
            }

            if ($amount->greaterThan($invoice->balance())) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ أكبر من المتبقي على الفاتورة ('.$invoice->balance()->format().').',
                ]);
            }

            $payment = TuitionPayment::create([
                'tenant_id' => $invoice->tenant_id,
                'tuition_invoice_id' => $invoice->id,
                'receipt_number' => $this->numbers->next($this->tenantOf($invoice), DocumentNumberService::RECEIPT),
                'method' => TuitionPaymentMethod::from($data['method']),
                'amount_piasters' => $amount->piasters,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => isset($data['paid_at']) ? CarbonImmutable::parse($data['paid_at']) : now(),
                'received_by' => $cashier->id,
            ]);

            $this->applyToInvoice($invoice, $amount->piasters);
            $this->ledger->postPaymentReceived($payment, $invoice);

            $this->audit->record(AuditAction::TuitionPaymentRecorded, $payment, [
                'invoice' => $invoice->number,
                'receipt' => $payment->receipt_number,
                'amount_piasters' => $amount->piasters,
                'method' => $payment->method->value,
            ], $cashier, $invoice->tenant_id);

            return $payment;
        });
    }

    /**
     * @throws ValidationException when the payment is already void.
     */
    public function voidPayment(TuitionPayment $payment, string $reason, User $by): TuitionPayment
    {
        return DB::transaction(function () use ($payment, $reason, $by) {
            $invoice = $this->lock($payment->invoice);
            $payment = TuitionPayment::withoutGlobalScopes()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->isVoid()) {
                throw ValidationException::withMessages(['reason' => 'هذا الإيصال ملغي بالفعل.']);
            }

            $payment->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);

            $this->applyToInvoice($invoice, -$payment->amount_piasters);
            $this->ledger->postPaymentVoided($payment, $invoice);

            $this->audit->record(AuditAction::TuitionPaymentVoided, $payment, [
                'invoice' => $invoice->number,
                'receipt' => $payment->receipt_number,
                'amount_piasters' => $payment->amount_piasters,
                'reason' => $reason,
            ], $by, $invoice->tenant_id);

            return $payment;
        });
    }

    /**
     * Cancel an invoice issued by mistake. Only possible with no live payments
     * (void those first), so the ledger never shows money against a void bill.
     *
     * @throws ValidationException
     */
    public function voidInvoice(TuitionInvoice $invoice, string $reason, User $by): TuitionInvoice
    {
        return DB::transaction(function () use ($invoice, $reason, $by) {
            $invoice = $this->lock($invoice);

            if ($invoice->status === TuitionInvoiceStatus::Void) {
                throw ValidationException::withMessages(['reason' => 'الفاتورة ملغاة بالفعل.']);
            }

            if ($invoice->paid_piasters > 0) {
                throw ValidationException::withMessages(['reason' => 'على الفاتورة دفعات مسجّلة. ألغِ الإيصالات أولاً.']);
            }

            $invoice->update([
                'status' => TuitionInvoiceStatus::Void,
                'billing_key' => null, // frees the period so the family can be re-billed
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
            ]);

            $this->ledger->postInvoiceVoided($invoice);

            $this->audit->record(AuditAction::TuitionInvoiceVoided, $invoice, [
                'invoice' => $invoice->number,
                'total_piasters' => $invoice->total_piasters,
                'reason' => $reason,
            ], $by, $invoice->tenant_id);

            return $invoice;
        });
    }

    private function lock(TuitionInvoice $invoice): TuitionInvoice
    {
        return TuitionInvoice::withoutGlobalScopes()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
    }

    private function applyToInvoice(TuitionInvoice $invoice, int $deltaPiasters): void
    {
        $paid = $invoice->paid_piasters + $deltaPiasters;

        $invoice->update([
            'paid_piasters' => $paid,
            'status' => match (true) {
                $paid >= $invoice->total_piasters => TuitionInvoiceStatus::Paid,
                $paid > 0 => TuitionInvoiceStatus::PartiallyPaid,
                default => TuitionInvoiceStatus::Open,
            },
        ]);
    }

    private function tenantOf(TuitionInvoice $invoice): Tenant
    {
        return Tenant::findOrFail($invoice->tenant_id);
    }
}
