<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\ChildController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\MyInvoiceController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\WardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| Clients authenticate with a Sanctum bearer token (PWA / mobile) or the
| first-party session. Every data route runs through the `tenant` middleware,
| which resolves the active nursery and enables tenant isolation.
|
*/

Route::prefix('v1/auth')->group(function () {
    Route::post('tokens', [AuthTokenController::class, 'store'])->middleware('throttle:6,1');
    Route::delete('tokens/current', [AuthTokenController::class, 'destroy'])->middleware('auth:sanctum');
});

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    // Guardian self-service: unified siblings view.
    Route::get('me/wards', [WardController::class, 'index']);

    // Bahga Pay: the guardian's own family invoices (payer only).
    Route::middleware('rollout:bahga-pay')->group(function () {
        Route::get('me/invoices', [MyInvoiceController::class, 'index']);
        Route::get('me/invoices/{invoice}', [MyInvoiceController::class, 'show']);
    });

    // Children.
    Route::get('children', [ChildController::class, 'index']);
    Route::post('children', [ChildController::class, 'store'])
        ->middleware('plan.quota:children');
    Route::get('children/{child}', [ChildController::class, 'show']);
    Route::patch('children/{child}', [ChildController::class, 'update']);

    // Guardians of a child (per-pair links).
    Route::post('children/{child}/guardians', [GuardianController::class, 'store']);
    Route::delete('children/{child}/guardians/{guardian}', [GuardianController::class, 'destroy']);

    // Attendance + pickup verification.
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);

    // Subscription / billing (owner & admin only).
    Route::get('subscription', [SubscriptionController::class, 'show']);
    Route::post('subscription/change-plan', [SubscriptionController::class, 'changePlan']);
});
