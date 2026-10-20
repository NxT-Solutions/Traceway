<?php

declare(strict_types=1);

use App\Account\ConnectionProvider;
use App\Auth\Connections\ConnectionFlow;
use App\Http\Controllers\Auth\StartConnectionController;
use App\Http\Controllers\PasskeyEndpointsController;
use App\Http\Controllers\Settings\AssistantsController;
use App\Http\Controllers\Settings\ConnectionsController;
use App\Http\Controllers\Settings\DevicesController;
use App\Http\Controllers\Settings\EmailChangeController;
use App\Http\Controllers\Settings\PrivacyController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ReleasesController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function (): void {
    Route::get('settings', fn (): RedirectResponse => redirect()->to('/settings/profile'));

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    // A new email waits until the rider opens the link sent to it.
    Route::get('settings/profile/email/{id}/{hash}', [EmailChangeController::class, 'confirm'])
        ->middleware('throttle:6,1')
        ->name('profile.email.confirm');
    Route::post('settings/profile/email', [EmailChangeController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('profile.email.resend');
    Route::delete('settings/profile/email', [EmailChangeController::class, 'destroy'])->name('profile.email.cancel');

    // Confirms it is the rider, then brings them back to Profile. A rider without a password deletes their account
    // this way, since there is no password to type in the dialog.
    Route::get('settings/profile/confirm', fn (): RedirectResponse => to_route('profile.edit'))
        ->middleware(RequirePassword::class)
        ->name('profile.confirm');

    Route::get('settings/assistants', [AssistantsController::class, 'edit'])->name('assistants.edit');

    Route::get('settings/privacy', [PrivacyController::class, 'edit'])->name('privacy.edit');
    Route::get('settings/privacy/export', [PrivacyController::class, 'export'])->middleware('throttle:export')->name('privacy.export');
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

    Route::get('settings/connections', [ConnectionsController::class, 'edit'])->name('connections.edit');
    // Adding or removing a way to log in waits until the email is confirmed, so an account opened with someone
    // else's address cannot keep a way in after its owner claims it.
    Route::get('settings/connections/{provider}/connect', [ConnectionsController::class, 'connect'])
        ->whereIn('provider', ConnectionProvider::slugs())
        ->middleware(['verified', RequirePassword::class, 'throttle:connections'])
        ->name('connections.connect');
    Route::delete('settings/connections/{provider}', [ConnectionsController::class, 'destroy'])
        ->whereIn('provider', ConnectionProvider::slugs())
        ->middleware(['verified', 'throttle:connections'])
        ->name('connections.destroy');

    // A rider without a password proves it is them with a connected service, as with a passkey.
    Route::get('user/confirm-password/{provider}', [StartConnectionController::class, 'confirm'])
        ->whereIn('provider', ConnectionProvider::slugs())
        ->middleware('throttle:connections')
        ->name('password.confirm.connection');

    // Send to device's links from before connections, open in a tab somewhere.
    Route::get('settings/services/{service}/connect', fn (Request $request, string $service): RedirectResponse => to_route('connections.connect', ['provider' => $service, 'return' => ConnectionFlow::safeReturn($request->query('return'))]))
        ->whereIn('service', ['wahoo', 'ride_with_gps'])
        ->name('services.connect');

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
