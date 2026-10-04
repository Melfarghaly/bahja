<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NurseryController as AdminNurseryController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Nursery\AttendanceController;
use App\Http\Controllers\Nursery\ChildController;
use App\Http\Controllers\Nursery\ChildImportController;
use App\Http\Controllers\Nursery\ClassroomController;
use App\Http\Controllers\Nursery\DashboardController;
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
