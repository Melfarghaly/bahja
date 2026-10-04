<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\ChildController;
use App\Http\Controllers\Api\V1\ClassroomController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\MyInvoiceController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\WardController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
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

// Payment gateway notifications: no auth, trust comes from the signature only.
Route::post('webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.payments');

Route::prefix('v1/auth')->group(function () {
    Route::post('tokens', [AuthTokenController::class, 'store'])->middleware('throttle:login');

    // Passwordless sign-in by SMS code (parents).
    Route::post('otp', [OtpController::class, 'store'])->middleware('throttle:otp-send');
    Route::post('otp/verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify');
    Route::delete('tokens/current', [AuthTokenController::class, 'destroy'])->middleware('auth:sanctum');
});

// Who am I, and in which nurseries (no X-Tenant-Id needed).
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('me', [ProfileController::class, 'show']);
    Route::patch('me', [ProfileController::class, 'update']);
});

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    // Guardian app: my children (custody-blocked links are invisible).
    Route::get('me/wards', [WardController::class, 'index']);
    Route::get('me/wards/{child}', [WardController::class, 'show']);
    Route::get('me/wards/{child}/attendance', [WardController::class, 'attendance']);
    Route::patch('me/wards/{child}/notifications', [WardController::class, 'updateNotifications']);

    // Bahga Pay: the guardian's own family invoices (payer only).
    Route::middleware('rollout:bahga-pay')->group(function () {
        Route::get('me/payment-methods', [MyInvoiceController::class, 'paymentMethods']);
        Route::get('me/invoices', [MyInvoiceController::class, 'index']);
        Route::get('me/invoices/{invoice}', [MyInvoiceController::class, 'show']);
        Route::post('me/invoices/{invoice}/checkout', [MyInvoiceController::class, 'checkout'])
            ->middleware(['entitled:auto_collection', 'throttle:checkout']);
    });

    // Staff: classrooms and children.
    Route::get('classrooms', [ClassroomController::class, 'index']);
    Route::get('children', [ChildController::class, 'index']);
    Route::post('children', [ChildController::class, 'store'])
        ->middleware('plan.quota:children');
    Route::get('children/{child}', [ChildController::class, 'show']);
    Route::patch('children/{child}', [ChildController::class, 'update']);

    // Guardians of a child (per-pair links).
    Route::post('children/{child}/guardians', [GuardianController::class, 'store']);
    Route::delete('children/{child}/guardians/{guardian}', [GuardianController::class, 'destroy']);

    // Attendance + pickup verification.
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);

    // Subscription / billing (owner & admin only).
    Route::get('subscription', [SubscriptionController::class, 'show']);
    Route::get('subscription/plans', [SubscriptionController::class, 'plans']);
    Route::post('subscription/change-plan', [SubscriptionController::class, 'changePlan']);
});
