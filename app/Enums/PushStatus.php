<?php

namespace App\Enums;

/**
 * Where a notification is in its delivery (the inbox copy exists either way).
 */
enum PushStatus: string
{
    case Pending = 'pending';
    case Deferred = 'deferred';     // waiting for quiet hours to end
    case Sent = 'sent';             // reached at least one device or phone
    case Skipped = 'skipped';       // nothing to send to, or turned off
    case Failed = 'failed';         // every attempt failed
}
