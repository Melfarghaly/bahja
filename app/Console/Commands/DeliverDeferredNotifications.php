<?php

namespace App\Console\Commands;

use App\Enums\PushStatus;
use App\Jobs\DeliverNotification;
use App\Models\UserNotification;
use App\Support\RowLevelSecurity;
use Illuminate\Console\Command;

/**
 * Every five minutes: send the notifications held back by quiet hours once
 * those hours are over.
 */
class DeliverDeferredNotifications extends Command
{
    protected $signature = 'notifications:deliver-deferred';

    protected $description = 'Send notifications that were held during quiet hours';

    public function handle(RowLevelSecurity $rls): int
    {
        $due = $rls->bypass(fn () => UserNotification::withoutGlobalScopes()
            ->where('push_status', PushStatus::Deferred->value)
            ->where('deliver_after', '<=', now())
            ->orderBy('id')
            ->limit(5000)
            ->get(['id', 'tenant_id']));

        foreach ($due as $notification) {
            DeliverNotification::dispatch($notification->id, $notification->tenant_id);
        }

        if ($due->isNotEmpty()) {
            $this->line("Queued {$due->count()} deferred notification(s).");
        }

        return self::SUCCESS;
    }
}
