<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Payments\Checkout\GatewayEvent;
use App\Services\Tuition\OnlinePaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Applies a verified, de-duplicated gateway event. Safe to retry: the
 * payment intent can only settle once.
 */
class ProcessGatewayEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public int $webhookEventId, public GatewayEvent $event)
    {
        $this->onQueue('payments');
    }

    public function handle(OnlinePaymentService $payments): void
    {
        $outcome = $payments->handle($this->event);

        WebhookEvent::whereKey($this->webhookEventId)->update([
            'outcome' => $outcome,
            'error' => null,
            'processed_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        WebhookEvent::whereKey($this->webhookEventId)->update([
            'outcome' => 'failed',
            'error' => mb_substr($e->getMessage(), 0, 2000),
        ]);
    }
}
