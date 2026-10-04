<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGatewayEvent;
use App\Models\WebhookEvent;
use App\Services\Payments\Checkout\CheckoutGatewayRegistry;
use App\Services\Payments\Exceptions\InvalidWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Receives payment gateway notifications. Unauthenticated by design — trust
 * comes only from the gateway signature. Responds fast; the work is queued.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private CheckoutGatewayRegistry $gateways) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        try {
            $gateway = $this->gateways->forProvider($provider);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $payload = ['body' => $request->json()->all(), 'query' => $request->query()];

        try {
            $event = $gateway->parseWebhook($request);
        } catch (InvalidWebhookSignature $e) {
            // Kept for forensics, never processed.
            WebhookEvent::create([
                'provider' => $provider,
                'event_id' => 'rejected:'.Str::uuid(),
                'signature_valid' => false,
                'payload' => $payload,
                'outcome' => 'rejected',
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'invalid signature'], 401);
        }

        // ON CONFLICT DO NOTHING: detects a retried event without raising an
        // error (which on PostgreSQL would abort any surrounding transaction).
        $inserted = WebhookEvent::query()->insertOrIgnore([
            'provider' => $provider,
            'event_id' => $event->eventId,
            'signature_valid' => true,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            // The gateway retried an event we already have: acknowledge, do nothing.
            return response()->json(['status' => 'duplicate']);
        }

        $webhookId = WebhookEvent::where('provider', $provider)->where('event_id', $event->eventId)->value('id');

        ProcessGatewayEvent::dispatch($webhookId, $event);

        return response()->json(['status' => 'accepted']);
    }
}
