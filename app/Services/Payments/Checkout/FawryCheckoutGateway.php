<?php

namespace App\Services\Payments\Checkout;

use App\Enums\PaymentGatewayName;
use App\Models\PaymentIntent;
use App\Models\TuitionInvoice;
use App\Models\User;
use App\Services\Payments\Exceptions\GatewayException;
use App\Services\Payments\Exceptions\InvalidWebhookSignature;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Fawry "Pay at Fawry": we create a charge and get a reference number the
 * parent pays at any Fawry outlet / app. Fawry then calls our server
 * notification (V2), signed with SHA-256. Verify both signature recipes
 * against Fawry's integration docs in staging before going live.
 */
class FawryCheckoutGateway implements CheckoutGateway
{
    private const PAYMENT_METHOD = 'PAYATFAWRY';

    /**
     * @param  array{base_url: string, merchant_code: ?string, secure_key: ?string, expiry_hours: int}  $config
     */
    public function __construct(private array $config) {}

    public function name(): PaymentGatewayName
    {
        return PaymentGatewayName::Fawry;
    }

    public function isConfigured(): bool
    {
        return filled($this->config['merchant_code']) && filled($this->config['secure_key']);
    }

    public function createCheckout(PaymentIntent $intent, TuitionInvoice $invoice, User $payer): CheckoutSession
    {
        $amount = $intent->amount()->toPounds();          // "1500.00" — exact, no floats
        $expiresAt = CarbonImmutable::now()->addHours($this->config['expiry_hours']);
        $profileId = (string) $payer->id;

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->acceptJson()
                ->timeout(15)
                ->post('/ECommerceWeb/Fawry/payments/charge', [
                    'merchantCode' => $this->config['merchant_code'],
                    'merchantRefNum' => $intent->merchant_reference,
                    'customerProfileId' => $profileId,
                    'customerName' => $payer->name,
                    'customerMobile' => $payer->phone,
                    'customerEmail' => $payer->email,
                    'paymentMethod' => self::PAYMENT_METHOD,
                    'amount' => $amount,
                    'currencyCode' => 'EGP',
                    'description' => "فاتورة {$invoice->number}",
                    'paymentExpiry' => $expiresAt->getTimestampMs(),
                    'chargeItems' => [[
                        'itemId' => $invoice->number,
                        'description' => "مصروفات {$invoice->period_start->format('Y-m')}",
                        'price' => $amount,
                        'quantity' => 1,
                    ]],
                    'signature' => hash('sha256', $this->config['merchant_code'].$intent->merchant_reference
                        .$profileId.self::PAYMENT_METHOD.$amount.$this->config['secure_key']),
                ]);
        } catch (ConnectionException $e) {
            throw new GatewayException('Fawry unreachable: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful() || (int) $response->json('statusCode') !== 200 || blank($response->json('referenceNumber'))) {
            throw new GatewayException('Fawry charge failed: '.mb_substr($response->body(), 0, 300));
        }

        return new CheckoutSession(
            gatewayReference: (string) $response->json('referenceNumber'),
            paymentCode: (string) $response->json('referenceNumber'),
            expiresAt: $expiresAt,
        );
    }

    public function parseWebhook(Request $request): GatewayEvent
    {
        $data = $request->json()->all();

        try {
            $paymentAmount = $this->decimal($data['paymentAmount'] ?? null);
            $orderAmount = $this->decimal($data['orderAmount'] ?? null);
        } catch (InvalidArgumentException) {
            throw new InvalidWebhookSignature('Fawry notification has malformed amounts.');
        }

        $expected = hash('sha256',
            ($data['fawryRefNumber'] ?? '').($data['merchantRefNumber'] ?? '')
            .$paymentAmount->toPounds().$orderAmount->toPounds()
            .($data['orderStatus'] ?? '').($data['paymentMethod'] ?? '')
            .($data['paymentRefrenceNumber'] ?? '').$this->config['secure_key']);

        if (blank($this->config['secure_key']) || ! hash_equals($expected, strtolower((string) ($data['messageSignature'] ?? '')))) {
            throw new InvalidWebhookSignature('Fawry signature mismatch.');
        }

        $status = (string) ($data['orderStatus'] ?? '');

        return new GatewayEvent(
            eventId: ($data['fawryRefNumber'] ?? '').':'.$status,
            merchantReference: $data['merchantRefNumber'] ?? null,
            successful: $status === 'PAID',
            final: in_array($status, ['PAID', 'EXPIRED', 'CANCELED', 'FAILED'], true),
            // The merchant's amount (order), excluding the fee Fawry charges the payer.
            amountPiasters: $orderAmount->piasters,
            gatewayReference: isset($data['fawryRefNumber']) ? (string) $data['fawryRefNumber'] : null,
        );
    }

    /**
     * Amounts arrive as JSON numbers or strings; parse exactly to piasters.
     */
    private function decimal(mixed $value): Money
    {
        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        return Money::fromPounds((string) $value);
    }
}
