<?php

namespace App\Services\Payments\Checkout;

use App\Enums\PaymentGatewayName;
use App\Models\PaymentIntent;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\Payments\Exceptions\GatewayException;
use App\Services\Payments\Exceptions\InvalidWebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Paymob "Intention" API + Unified Checkout (cards and mobile wallets).
 *
 * Webhook: Paymob's "transaction processed callback" signs a fixed list of
 * transaction fields with HMAC-SHA512 and sends it as ?hmac=. Verify the field
 * list against the Paymob dashboard docs in sandbox before going live.
 */
class PaymobCheckoutGateway implements CheckoutGateway
{
    /**
     * Fields concatenated (in this order) for the transaction HMAC.
     */
    private const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
        'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
        'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];

    /**
     * @param  array{base_url: string, secret_key: ?string, public_key: ?string, hmac_secret: ?string, integration_ids: array<int, int>, expiry_minutes: int}  $config
     */
    public function __construct(private array $config) {}

    public function name(): PaymentGatewayName
    {
        return PaymentGatewayName::Paymob;
    }

    public function isConfigured(): bool
    {
        return filled($this->config['secret_key']) && filled($this->config['public_key'])
            && filled($this->config['hmac_secret']) && $this->config['integration_ids'] !== [];
    }

    public function createCheckout(PaymentIntent $intent, TuitionInvoice $invoice, User $payer): CheckoutSession
    {
        [$first, $last] = array_pad(explode(' ', trim($payer->name), 2), 2, '-');

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->withHeaders(['Authorization' => 'Token '.$this->config['secret_key']])
                ->acceptJson()
                ->timeout(15)
                ->post('/v1/intention/', [
                    'amount' => $intent->amount_piasters,
                    'currency' => 'EGP',
                    'payment_methods' => $this->config['integration_ids'],
                    'items' => [[
                        'name' => "فاتورة {$invoice->number}",
                        'amount' => $intent->amount_piasters,
                        'quantity' => 1,
                    ]],
                    'billing_data' => [
                        'first_name' => $first,
                        'last_name' => $last,
                        'phone_number' => $payer->phone ?: 'NA',
                        'email' => $payer->email ?: 'no-reply@bahga.app',
                    ],
                    'special_reference' => $intent->merchant_reference,
                    'expiration' => $this->config['expiry_minutes'] * 60,
                    'notification_url' => route('webhooks.payments', PaymentGatewayName::Paymob->value),
                    'redirection_url' => route('pay.return'),
                ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('Paymob unreachable: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful() || blank($response->json('client_secret'))) {
            throw new GatewayException('Paymob intention failed: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 300));
        }

        return new CheckoutSession(
            gatewayReference: (string) $response->json('id'),
            url: rtrim($this->config['base_url'], '/').'/unifiedcheckout/?'.http_build_query([
                'publicKey' => $this->config['public_key'],
                'clientSecret' => $response->json('client_secret'),
            ]),
            expiresAt: CarbonImmutable::now()->addMinutes($this->config['expiry_minutes']),
        );
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $transaction = $request->json('obj');

        if (! is_array($transaction) || ! $this->signatureMatches($transaction, (string) $request->query('hmac'))) {
            throw new InvalidWebhookSignature('Paymob HMAC mismatch.');
        }

        $isTransaction = $request->json('type') === 'TRANSACTION';
        $pending = (bool) Arr::get($transaction, 'pending');

        return new GatewayEvent(
            eventId: 'txn:'.Arr::get($transaction, 'id'),
            merchantReference: $isTransaction ? Arr::get($transaction, 'order.merchant_order_id') : null,
            successful: (bool) Arr::get($transaction, 'success') && ! $pending
                && ! Arr::get($transaction, 'is_voided') && ! Arr::get($transaction, 'is_refunded'),
            final: ! $pending,
            amountPiasters: (int) Arr::get($transaction, 'amount_cents'),
            gatewayReference: (string) Arr::get($transaction, 'id'),
        );
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function signatureMatches(array $transaction, string $received): bool
    {
        if ($received === '' || blank($this->config['hmac_secret'])) {
            return false;
        }

        $message = implode('', array_map(function (string $field) use ($transaction) {
            $value = Arr::get($transaction, $field);

            return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }, self::HMAC_FIELDS));

        return hash_equals(hash_hmac('sha512', $message, $this->config['hmac_secret']), strtolower($received));
    }
}
