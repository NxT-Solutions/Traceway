<?php

declare(strict_types=1);

use App\Http\Controllers\Device\DeviceCourseController;
use App\Http\Controllers\Device\DevicePairingController;
use App\Http\Controllers\Device\DeviceRoutesController;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('device')->group(function (): void {
    Route::post('pairings', [DevicePairingController::class, 'start'])
        ->middleware('throttle:device-pairing')
        ->name('device.pairings.start');
    Route::post('pairings/claim', [DevicePairingController::class, 'claim'])
        ->middleware('throttle:device')
        ->name('device.pairings.claim');

    Route::middleware(['throttle:device', AuthenticateDevice::class])->group(function (): void {
        Route::get('routes', DeviceRoutesController::class)->name('device.routes');
        Route::get('routes/{savedRoute}/fit', [DeviceCourseController::class, 'fit'])
            ->whereUuid('savedRoute')
            ->name('device.routes.fit');
        Route::get('routes/{savedRoute}/fit/{leg}', [DeviceCourseController::class, 'fitLeg'])
            ->whereUuid('savedRoute')
            ->whereNumber('leg')
            ->name('device.routes.fit.leg');
    });
});
