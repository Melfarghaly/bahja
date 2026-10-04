<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Bahga Pay: issue the month's family invoices on the 1st (Cairo time).
// Idempotent, so a retry or a manual run never double-bills.
Schedule::command('tuition:generate')
    ->monthlyOn(1, '06:00')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->onOneServer();

// Bahga Pay: daily payment reminders at a civil hour (no SMS at night).
Schedule::command('tuition:remind')
    ->dailyAt('10:00')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->onOneServer();

// Safe Pickup: children still at the nursery after its pickup deadline.
Schedule::command('pickup:late-alerts')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Notifications held back by a nursery's quiet hours go out when they end.
Schedule::command('notifications:deliver-deferred')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Daily Wall: photos past the plan's retention (Free: 30 days) are erased.
Schedule::command('wall:prune-media')
    ->dailyAt('03:30')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->onOneServer();
