<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminActivityController;
use App\Http\Controllers\Admin\AdminImpersonationController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminTrafficController;
use App\Http\Controllers\Admin\AdminUsersController;
use Illuminate\Support\Facades\Route;

// The admin area. Each route asks the gate for its permission, so a Super Admin passes them all.
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', AdminOverviewController::class)->middleware('can:view-analytics')->name('overview');
    Route::get('traffic', AdminTrafficController::class)->middleware('can:view-analytics')->name('traffic');

    Route::get('activity', AdminActivityController::class)->middleware('can:view-users')->name('activity');

    Route::get('users', [AdminUsersController::class, 'index'])->middleware('can:view-users')->name('users.index');
    Route::get('users/create', [AdminUsersController::class, 'create'])->middleware('can:manage-users')->name('users.create');
    Route::post('users', [AdminUsersController::class, 'store'])->middleware(['can:manage-users', 'throttle:20,1'])->name('users.store');
    Route::get('users/{rider}', [AdminUsersController::class, 'show'])->where('rider', '[1-9][0-9]*')->middleware('can:view-users')->name('users.show');
    Route::patch('users/{rider}', [AdminUsersController::class, 'update'])->where('rider', '[1-9][0-9]*')->middleware('can:manage-users')->name('users.update');
    Route::delete('users/{rider}', [AdminUsersController::class, 'destroy'])->where('rider', '[1-9][0-9]*')->middleware('can:manage-users')->name('users.destroy');
    Route::put('users/{rider}/role', [AdminUsersController::class, 'role'])->where('rider', '[1-9][0-9]*')->middleware('can:assign-roles')->name('users.role');
    Route::delete('users/{rider}/two-factor', [AdminUsersController::class, 'resetTwoFactor'])->where('rider', '[1-9][0-9]*')->middleware('can:manage-users')->name('users.two-factor.destroy');
    Route::post('users/{rider}/impersonate', [AdminImpersonationController::class, 'store'])->where('rider', '[1-9][0-9]*')->middleware('can:impersonate')->name('users.impersonate');
    Route::delete('users/{rider}/sessions', [AdminUsersController::class, 'endSessions'])->where('rider', '[1-9][0-9]*')->middleware('can:manage-users')->name('users.sessions.destroy');
});

// Back to yourself after acting as a rider. The signed-in account is the rider's, which holds no admin permission
// and may not have confirmed its email, so this needs only a session.
Route::delete('admin/impersonation', [AdminImpersonationController::class, 'destroy'])
    ->middleware('auth')
    ->name('admin.impersonation.destroy');
