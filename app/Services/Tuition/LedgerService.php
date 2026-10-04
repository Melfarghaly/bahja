<?php

namespace App\Services\Tuition;

use App\Enums\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\TuitionPayment;
use Illuminate\Support\Str;
use LogicException;

/**
 * Double-entry postings for tuition. Every posting is a set of lines sharing a
 * transaction id whose debits equal its credits; history is never edited —
 * mistakes are corrected with reversing postings.
 *
 *   Invoice issued:   Dr Receivable (total) + Dr Discounts (discount) = Cr Revenue (subtotal)
 *   Payment received: Dr Cash|Bank = Cr Receivable
 *   Voids post the exact mirror image.
 */
class LedgerService
{
    public function postInvoiceIssued(TuitionInvoice $invoice): string
    {
        return $this->post($invoice->tenant_id, "إصدار الفاتورة {$invoice->number}", [
            [LedgerAccount::Receivable, $invoice->total_piasters, 0],
            [LedgerAccount::Discounts, $invoice->discount_piasters, 0],
            [LedgerAccount::TuitionRevenue, 0, $invoice->subtotal_piasters],
        ], invoice: $invoice);
    }

    public function postInvoiceVoided(TuitionInvoice $invoice): string
    {
        return $this->post($invoice->tenant_id, "إلغاء الفاتورة {$invoice->number}", [
            [LedgerAccount::TuitionRevenue, $invoice->subtotal_piasters, 0],
            [LedgerAccount::Receivable, 0, $invoice->total_piasters],
            [LedgerAccount::Discounts, 0, $invoice->discount_piasters],
        ], invoice: $invoice);
    }

    public function postPaymentReceived(TuitionPayment $payment, TuitionInvoice $invoice): string
    {
        return $this->post($payment->tenant_id, "تحصيل الإيصال {$payment->receipt_number}", [
            [$payment->method->ledgerAccount(), $payment->amount_piasters, 0],
            [LedgerAccount::Receivable, 0, $payment->amount_piasters],
        ], invoice: $invoice, payment: $payment);
    }

    public function postPaymentVoided(TuitionPayment $payment, TuitionInvoice $invoice): string
    {
        return $this->post($payment->tenant_id, "إلغاء الإيصال {$payment->receipt_number}", [
            [LedgerAccount::Receivable, $payment->amount_piasters, 0],
            [$payment->method->ledgerAccount(), 0, $payment->amount_piasters],
        ], invoice: $invoice, payment: $payment);
    }

    /**
     * Balance per account for a nursery: debits − credits (piasters).
     *
     * @return array<string, int>
     */
    public function balances(Tenant $tenant): array
    {
        return LedgerEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->selectRaw('account, sum(debit_piasters) - sum(credit_piasters) as balance')
            ->groupBy('account')
            ->pluck('balance', 'account')
            ->map(fn ($balance) => (int) $balance)
            ->all();
    }

    /**
     * What the ledger says is still owed on an invoice.
     */
    public function receivableOn(TuitionInvoice $invoice): int
    {
        return (int) LedgerEntry::withoutGlobalScopes()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('tuition_invoice_id', $invoice->id)
            ->where('account', LedgerAccount::Receivable->value)
            ->selectRaw('coalesce(sum(debit_piasters) - sum(credit_piasters), 0) as balance')
            ->value('balance');
    }

    /**
     * @param  array<int, array{0: LedgerAccount, 1: int, 2: int}>  $lines  [account, debit, credit]
     *
     * @throws LogicException when the posting does not balance.
     */
    private function post(int $tenantId, string $description, array $lines, ?TuitionInvoice $invoice = null, ?TuitionPayment $payment = null): string
    {
        // Zero lines carry no information (e.g. an invoice without discounts).
        $lines = array_values(array_filter($lines, fn (array $line) => $line[1] !== 0 || $line[2] !== 0));

        $debits = array_sum(array_column($lines, 1));
        $credits = array_sum(array_column($lines, 2));

        if ($lines === [] || $debits !== $credits) {
            throw new LogicException("Unbalanced ledger posting [{$description}]: Dr {$debits} / Cr {$credits}.");
        }

        $transactionId = (string) Str::uuid();
        $postedAt = now();

        foreach ($lines as [$account, $debit, $credit]) {
            LedgerEntry::create([
                'tenant_id' => $tenantId,
                'transaction_id' => $transactionId,
                'account' => $account,
                'debit_piasters' => $debit,
                'credit_piasters' => $credit,
                'tuition_invoice_id' => $invoice?->id,
                'tuition_payment_id' => $payment?->id,
                'payer_id' => $invoice?->payer_id,
                'description' => $description,
                'posted_at' => $postedAt,
            ]);
        }

        return $transactionId;
    }
}
