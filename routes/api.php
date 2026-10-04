<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\ChildController;
use App\Http\Controllers\Api\V1\ClassroomController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\GuardianPickupController;
use App\Http\Controllers\Api\V1\MyInvoiceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\PickupController;
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

    // Push notifications: the app's FCM token (one per install).
    Route::post('me/devices', [DeviceController::class, 'store']);
    Route::delete('me/devices', [DeviceController::class, 'destroy']);
});

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    // In-app notifications in this nursery (every role).
    Route::get('me/notifications', [NotificationController::class, 'index']);
    Route::post('me/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('me/notifications/{notification}/read', [NotificationController::class, 'read'])->whereNumber('notification');

    // Guardian app: my children (custody-blocked links are invisible).
    Route::get('me/wards', [WardController::class, 'index']);
    Route::get('me/wards/{child}', [WardController::class, 'show']);
    Route::get('me/wards/{child}/attendance', [WardController::class, 'attendance']);
    Route::patch('me/wards/{child}/notifications', [WardController::class, 'updateNotifications']);

    // Safe Pickup 2.0 (released per nursery; QR and passes need the Basic plan or above).
    Route::middleware(['rollout:safe-pickup-v2', 'entitled:pickup_passes'])->group(function () {
        Route::get('me/pickup-code', [GuardianPickupController::class, 'code']);
        Route::get('me/wards/{child}/pickup-passes', [GuardianPickupController::class, 'index']);
        Route::post('me/wards/{child}/pickup-passes', [GuardianPickupController::class, 'store']);
        Route::delete('me/pickup-passes/{pass}', [GuardianPickupController::class, 'destroy']);
        Route::post('attendance/pickup/verify', [PickupController::class, 'verify'])->middleware('throttle:pickup-verify');
    });

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
    Route::post('attendance/check-in/bulk', [AttendanceController::class, 'bulkCheckIn']);
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);

    // Subscription / billing (owner & admin only).
    Route::get('subscription', [SubscriptionController::class, 'show']);
    Route::get('subscription/plans', [SubscriptionController::class, 'plans']);
    Route::post('subscription/change-plan', [SubscriptionController::class, 'changePlan']);
});
