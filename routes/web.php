<?php

use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NurseryController as AdminNurseryController;
use App\Http\Controllers\Admin\NurseryEntitlementController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RolloutController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Nursery\AttendanceController;
use App\Http\Controllers\Nursery\ChildController;
use App\Http\Controllers\Nursery\ChildImportController;
use App\Http\Controllers\Nursery\ClassroomController;
use App\Http\Controllers\Nursery\DashboardController;
use App\Http\Controllers\Nursery\Finance\ChildFeeController;
use App\Http\Controllers\Nursery\Finance\FeeSetupController;
use App\Http\Controllers\Nursery\Finance\TuitionInvoiceController;
use App\Http\Controllers\Nursery\GuardianController;
use App\Http\Controllers\Nursery\SettingsController;
use App\Http\Controllers\Nursery\SubscriptionController;
use App\Http\Controllers\Nursery\TeacherController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

/*
|--------------------------------------------------------------------------
| Nursery management (tenant owner / admin / teacher)
|--------------------------------------------------------------------------
| Tenant-scoped: IdentifyTenant sets the active nursery; nursery.staff gates
| access. Owner/admin-only screens are additionally wrapped in nursery.admin.
*/
Route::middleware(['auth', 'tenant', 'nursery.staff'])
    ->prefix('app')
    ->name('nursery.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Children + guardians (staff)
        Route::get('children', [ChildController::class, 'index'])->name('children.index');
        Route::get('children/create', [ChildController::class, 'create'])->name('children.create');
        Route::get('children/import', [ChildImportController::class, 'form'])->name('children.import.form');
        Route::post('children/import', [ChildImportController::class, 'store'])->name('children.import.store');
        Route::get('children/import/template', [ChildImportController::class, 'template'])->name('children.import.template');
        Route::post('children', [ChildController::class, 'store'])->name('children.store');
        Route::get('children/{child}', [ChildController::class, 'show'])->name('children.show');
        Route::get('children/{child}/edit', [ChildController::class, 'edit'])->name('children.edit');
        Route::put('children/{child}', [ChildController::class, 'update'])->name('children.update');
        Route::post('children/{child}/guardians', [GuardianController::class, 'store'])->name('children.guardians.store');
        Route::delete('children/{child}/guardians/{guardian}', [GuardianController::class, 'destroy'])->name('children.guardians.destroy');

        // Attendance (staff)
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance/{child}/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
        Route::post('attendance/{child}/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');

        // Owner / admin only
        Route::middleware('nursery.admin')->group(function () {
            Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
            Route::post('teachers', [TeacherController::class, 'store'])->name('teachers.store');
            Route::put('teachers/{teacher}', [TeacherController::class, 'update'])->name('teachers.update');
            Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy'])->name('teachers.destroy');

            Route::get('classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
            Route::post('classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
            Route::put('classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
            Route::delete('classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');

            Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
            Route::post('subscription/change-plan', [SubscriptionController::class, 'changePlan'])->name('subscription.change-plan');
            Route::post('subscription/coupon', [SubscriptionController::class, 'redeemCoupon'])->name('subscription.coupon');

            // Bahga Pay — tuition billing (released per nursery, see admin rollouts).
            Route::middleware(['rollout:bahga-pay', 'entitled:finance_ledger'])
                ->prefix('finance')
                ->name('finance.')
                ->group(function () {
                    Route::get('setup', [FeeSetupController::class, 'index'])->name('setup');
                    Route::post('fee-plans', [FeeSetupController::class, 'storePlan'])->name('fee-plans.store');
                    Route::put('fee-plans/{feePlan}', [FeeSetupController::class, 'updatePlan'])->name('fee-plans.update');
                    Route::patch('fee-plans/{feePlan}/active', [FeeSetupController::class, 'togglePlan'])->name('fee-plans.toggle');
                    Route::post('discounts', [FeeSetupController::class, 'storeDiscount'])->name('discounts.store');
                    Route::put('discounts/{feeDiscount}', [FeeSetupController::class, 'updateDiscount'])->name('discounts.update');
                    Route::patch('discounts/{feeDiscount}/active', [FeeSetupController::class, 'toggleDiscount'])->name('discounts.toggle');

                    Route::get('invoices', [TuitionInvoiceController::class, 'index'])->name('invoices.index');
                    Route::post('invoices/generate', [TuitionInvoiceController::class, 'generate'])->name('invoices.generate');
                    Route::get('invoices/{invoice}', [TuitionInvoiceController::class, 'show'])->name('invoices.show');

                    Route::post('children/{child}/fee-plans', [ChildFeeController::class, 'store'])->name('child-fees.store');
                    Route::patch('fee-assignments/{childFeePlan}/end', [ChildFeeController::class, 'end'])->name('child-fees.end');
                });

            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });

/*
|--------------------------------------------------------------------------
| Super Admin (platform operator)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('nurseries', [AdminNurseryController::class, 'index'])->name('nurseries.index');
        Route::get('nurseries/{tenant}', [AdminNurseryController::class, 'show'])->name('nurseries.show');
        Route::patch('nurseries/{tenant}/suspend', [AdminNurseryController::class, 'suspend'])->name('nurseries.suspend');
        Route::patch('nurseries/{tenant}/activate', [AdminNurseryController::class, 'activate'])->name('nurseries.activate');

        // Entitlements: overrides + add-ons. Child bindings are scoped to {tenant}.
        Route::scopeBindings()->group(function () {
            Route::post('nurseries/{tenant}/overrides', [NurseryEntitlementController::class, 'storeOverride'])->name('nurseries.overrides.store');
            Route::delete('nurseries/{tenant}/overrides/{entitlementOverride}', [NurseryEntitlementController::class, 'destroyOverride'])->name('nurseries.overrides.destroy');
            Route::post('nurseries/{tenant}/addons', [NurseryEntitlementController::class, 'storeAddon'])->name('nurseries.addons.store');
            Route::delete('nurseries/{tenant}/addons/{addon}', [NurseryEntitlementController::class, 'destroyAddon'])->name('nurseries.addons.destroy');
        });

        // Gradual release of V2 modules (Pennant).
        Route::get('rollouts', [RolloutController::class, 'index'])->name('rollouts.index');
        Route::patch('rollouts/{flag}', [RolloutController::class, 'updateEveryone'])->name('rollouts.everyone');
        Route::patch('nurseries/{tenant}/rollouts/{flag}', [RolloutController::class, 'updateTenant'])->name('nurseries.rollouts.update');

        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::get('coupons/create', [CouponController::class, 'create'])->name('coupons.create');
        Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::patch('coupons/{coupon}/status', [CouponController::class, 'updateStatus'])->name('coupons.status');

        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('plans/create', [PlanController::class, 'create'])->name('plans.create');
        Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
        Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
        Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/super-admin', [UserController::class, 'toggleSuperAdmin'])->name('users.toggle-super-admin');

        Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    });

require __DIR__.'/auth.php';
