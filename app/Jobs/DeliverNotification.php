<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one inbox notification by push / SMS, inside its nursery's context.
 */
class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public function __construct(public int $notificationId, public int $tenantId)
    {
        $this->onQueue('notifications');
    }

    public function handle(NotificationDispatcher $dispatcher, TenantContext $context): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $previous = $context->get();
        $context->set($tenant);

        try {
            $notification = UserNotification::find($this->notificationId);
            if ($notification !== null) {
                $dispatcher->deliver($notification);
            }
        } finally {
            $previous ? $context->set($previous) : $context->forget();
        }
    }
}
