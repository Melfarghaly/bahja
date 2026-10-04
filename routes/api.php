<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\ChildController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\WardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| All routes are authenticated and run through the `tenant` middleware, which
| resolves the active nursery and enables tenant isolation. NOTE: token auth
| (Laravel Sanctum) is the recommended addition for the mobile/SPA client —
| run `php artisan install:api` and swap `auth` for `auth:sanctum`.
|
*/

Route::prefix('v1')->middleware(['auth', 'tenant'])->group(function () {
    // Guardian self-service: unified siblings view.
    Route::get('me/wards', [WardController::class, 'index']);

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
