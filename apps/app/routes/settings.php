<?php

declare(strict_types=1);

use App\Http\Controllers\PasskeyEndpointsController;
use App\Http\Controllers\Settings\AssistantsController;
use App\Http\Controllers\Settings\DevicesController;
use App\Http\Controllers\Settings\PrivacyController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ReleasesController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function (): void {
    Route::get('settings', fn (): RedirectResponse => redirect()->to('/settings/profile'));

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/assistants', [AssistantsController::class, 'edit'])->name('assistants.edit');

    Route::get('settings/privacy', [PrivacyController::class, 'edit'])->name('privacy.edit');
    Route::get('settings/privacy/export', [PrivacyController::class, 'export'])->name('privacy.export');
    Route::delete('settings/privacy/apps/{client}', [PrivacyController::class, 'disconnect'])
        ->whereUuid('client')
        ->name('privacy.apps.disconnect');

    Route::get('settings/devices', [DevicesController::class, 'edit'])->name('devices.edit');
    Route::post('settings/devices', [DevicesController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('devices.store');
    Route::delete('settings/devices/{deviceLink}', [DevicesController::class, 'destroy'])
        ->whereUuid('deviceLink')
        ->name('devices.destroy');

    Route::get('settings/whats-new', [ReleasesController::class, 'index'])->name('releases.index');
    Route::post('settings/whats-new/read', [ReleasesController::class, 'readAll'])
        ->middleware('throttle:30,1')
        ->name('releases.read-all');
    Route::post('settings/whats-new/{release}/read', [ReleasesController::class, 'read'])
        ->whereNumber('release')
        ->middleware('throttle:60,1')
        ->name('releases.read');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', fn () => Inertia::render('settings/appearance'))->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', PasskeyEndpointsController::class)->name('well-known.passkeys');
