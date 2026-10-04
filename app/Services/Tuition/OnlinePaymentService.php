<?php

namespace App\Services\Tuition;

use App\Enums\AuditAction;
use App\Enums\PaymentGatewayName;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use App\Models\Tenant;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Payments\Checkout\CheckoutGatewayRegistry;
use App\Services\Payments\Checkout\GatewayEvent;
use App\Services\Payments\Exceptions\GatewayException;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Online tuition payments end to end:
 *   1. a parent opens a checkout → a PaymentIntent for the invoice's balance;
 *   2. the gateway confirms by webhook → the intent settles exactly once and a
 *      payment is recorded through TuitionPaymentService (ledger, receipt…).
 *
 * Idempotency layers: webhook_events (provider, event_id) unique; the intent
 * row is locked and only a Pending intent can change; tuition_payments has a
 * unique payment_intent_id.
 */
class OnlinePaymentService
{
    public function __construct(
        private CheckoutGatewayRegistry $gateways,
        private TuitionPaymentService $payments,
        private TenantContext $tenantContext,
        private AuditLogger $audit,
    ) {}

    /**
     * Open (or reuse) a checkout for the full outstanding balance.
     *
     * @throws ValidationException when the invoice can't be paid online now.
     */
    public function startCheckout(TuitionInvoice $invoice, User $payer, PaymentGatewayName $gatewayName): PaymentIntent
    {
        $gateway = $this->gateways->for($gatewayName);

        if (! $gateway->isConfigured()) {
            throw ValidationException::withMessages(['gateway' => 'طريقة الدفع هذه غير متاحة حالياً.']);
        }

        if (! $invoice->status->isCollectible() || ! $invoice->balance()->isPositive()) {
            throw ValidationException::withMessages(['invoice' => 'لا يوجد مبلغ مستحق على هذه الفاتورة.']);
        }

        // Reuse a live checkout for the same amount instead of minting a new
        // Fawry code / Paymob session on every tap.
        $reusable = PaymentIntent::where('tuition_invoice_id', $invoice->id)
            ->where('gateway', $gatewayName->value)
            ->where('amount_piasters', $invoice->balance()->piasters)
            ->where('status', PaymentIntentStatus::Pending->value)
            ->latest('id')
            ->get()
            ->first(fn (PaymentIntent $intent) => $intent->isReusable());

        if ($reusable !== null) {
            return $reusable;
        }

        $intent = PaymentIntent::create([
            'tenant_id' => $invoice->tenant_id,
            'tuition_invoice_id' => $invoice->id,
            'payer_id' => $payer->id,
            'gateway' => $gatewayName,
            'amount_piasters' => $invoice->balance()->piasters,
            'merchant_reference' => PaymentIntent::newMerchantReference($invoice->tenant_id),
            'status' => PaymentIntentStatus::Pending,
        ]);

        try {
            $session = $gateway->createCheckout($intent, $invoice, $payer);
        } catch (GatewayException $e) {
            report($e);
            $intent->update(['status' => PaymentIntentStatus::Failed, 'failure_reason' => mb_substr($e->getMessage(), 0, 255)]);

            throw ValidationException::withMessages(['gateway' => 'تعذّر الاتصال ببوابة الدفع. حاول مرة أخرى بعد قليل.']);
        }

        $intent->update([
            'gateway_reference' => $session->gatewayReference,
            'checkout_url' => $session->url,
            'payment_code' => $session->paymentCode,
            'expires_at' => $session->expiresAt,
        ]);

        return $intent;
    }

    /**
     * Apply a verified gateway event. Returns the outcome recorded on the webhook.
     */
    public function handle(GatewayEvent $event): string
    {
        if ($event->merchantReference === null) {
            return 'ignored';
        }

        $tenantId = PaymentIntent::tenantIdFromReference($event->merchantReference);
        $tenant = $tenantId !== null ? Tenant::find($tenantId) : null;

        if ($tenant === null) {
            return 'unknown_reference';
        }

        // Work inside the nursery's context: TenantScope + Row-Level Security.
        $this->tenantContext->set($tenant);

        try {
            return DB::transaction(function () use ($event) {
                $intent = PaymentIntent::where('merchant_reference', $event->merchantReference)->lockForUpdate()->first();

                if ($intent === null) {
                    return 'unknown_reference';
                }

                if ($intent->status !== PaymentIntentStatus::Pending) {
                    return 'duplicate';
                }

                if (! $event->final) {
                    return 'ignored';
                }

                if (! $event->successful) {
                    $intent->update(['status' => PaymentIntentStatus::Failed, 'completed_at' => now(), 'failure_reason' => 'Declined by gateway']);

                    return 'failed';
                }

                return $this->settle($intent, $event);
            });
        } finally {
            $this->tenantContext->forget();
        }
    }

    private function settle(PaymentIntent $intent, GatewayEvent $event): string
    {
        $invoice = $intent->invoice;

        // Money arrived but can't be applied cleanly: never guess — park it for a human.
        $problem = match (true) {
            $event->amountPiasters !== $intent->amount_piasters => 'المبلغ المدفوع لا يطابق المبلغ المطلوب',
            ! $invoice->status->isCollectible() => 'الفاتورة '.$invoice->status->label().' عند وصول الدفعة',
            $event->amountPiasters > $invoice->balance()->piasters => 'المبلغ أكبر من المتبقي (سُدّد جزء بطريقة أخرى)',
            default => null,
        };

        if ($problem !== null) {
            $intent->update(['status' => PaymentIntentStatus::NeedsReview, 'completed_at' => now(), 'failure_reason' => $problem]);

            $this->audit->record(AuditAction::OnlinePaymentNeedsReview, $intent, [
                'invoice' => $invoice->number,
                'reason' => $problem,
                'paid_piasters' => $event->amountPiasters,
                'gateway_reference' => $event->gatewayReference,
            ], tenantId: $intent->tenant_id);

            return 'needs_review';
        }

        $this->payments->recordOnline($invoice, $intent, $event->gatewayReference);

        $intent->update(['status' => PaymentIntentStatus::Succeeded, 'completed_at' => now()]);

        return 'applied';
    }
}
