<?php

declare(strict_types=1);

use App\Domain\Courses\CourseFormat;
use App\Domain\Usage\UsageMetric;
use App\Http\Controllers\Device\DeviceCourseController;
use App\Http\Controllers\Device\DevicePairingController;
use App\Http\Controllers\Device\DeviceRouteLineController;
use App\Http\Controllers\Device\DeviceRoutesController;
use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\CountUsage;
use App\Http\Middleware\NoteLastSeen;
use Illuminate\Support\Facades\Route;

Route::prefix('device')->group(function (): void {
    Route::post('pairings', [DevicePairingController::class, 'start'])
        ->middleware('throttle:device-pairing')
        ->name('device.pairings.start');
    Route::post('pairings/claim', [DevicePairingController::class, 'claim'])
        ->middleware('throttle:device')
        ->name('device.pairings.claim');

    Route::middleware(['throttle:device', AuthenticateDevice::class, NoteLastSeen::class])->group(function (): void {
        Route::get('routes', DeviceRoutesController::class)->middleware(CountUsage::as(UsageMetric::DeviceSyncs))->name('device.routes');
        Route::get('routes/{savedRoute}/line', [DeviceRouteLineController::class, 'route'])
            ->whereUuid('savedRoute')
            ->name('device.routes.line');
        Route::get('routes/{savedRoute}/line/{leg}', [DeviceRouteLineController::class, 'leg'])
            ->whereUuid('savedRoute')
            ->whereNumber('leg')
            ->name('device.routes.line.leg');
        Route::get('routes/{savedRoute}/{format}', [DeviceCourseController::class, 'course'])
            ->whereUuid('savedRoute')
            ->whereIn('format', array_column(CourseFormat::cases(), 'value'))
            ->middleware(CountUsage::as(UsageMetric::DeviceDownloads))
            ->name('device.routes.course');
        Route::get('routes/{savedRoute}/{format}/{leg}', [DeviceCourseController::class, 'leg'])
            ->whereUuid('savedRoute')
            ->whereIn('format', array_column(CourseFormat::cases(), 'value'))
            ->whereNumber('leg')
            ->middleware(CountUsage::as(UsageMetric::DeviceDownloads))
            ->name('device.routes.course.leg');
    });
});
