<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\BuildRouteController;
use App\Http\Controllers\DestroySavedRouteController;
use App\Http\Controllers\ExportFitCourseController;
use App\Http\Controllers\ExportSharedCourseController;
use App\Http\Controllers\ImportRouteController;
use App\Http\Controllers\PreviewMultisportController;
use App\Http\Controllers\PreviewRouteController;
use App\Http\Controllers\RedoImportedRouteController;
use App\Http\Controllers\RouteDetailsController;
use App\Http\Controllers\RouteStopsController;
use App\Http\Controllers\SavedRouteLibraryController;
use App\Http\Controllers\ShowSavedRouteDetailsController;
use App\Http\Controllers\ShowSharedRouteController;
use App\Http\Controllers\SnapPointController;
use App\Http\Controllers\StoreSavedRouteController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('login/demo/{rider}', DemoLoginController::class)
    ->middleware('guest')
    ->name('login.demo');

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::get('share/{savedRoute}', ShowSharedRouteController::class)
    ->middleware('signed')
    ->name('routes.share');
Route::get('share/{savedRoute}/gpx', [ExportSharedCourseController::class, 'gpx'])
    ->middleware('signed')
    ->name('routes.share.gpx');
Route::get('share/{savedRoute}/fit', [ExportSharedCourseController::class, 'fit'])
    ->middleware('signed')
    ->name('routes.share.fit');
Route::get('share/{savedRoute}/fit/{leg}', [ExportSharedCourseController::class, 'fitLeg'])
    ->whereNumber('leg')
    ->middleware('signed')
    ->name('routes.share.fit.leg');

Route::get('docs/public', fn (): RedirectResponse => redirect()->to('/docs'));
Route::get('docs/account', fn (): RedirectResponse => redirect()->to('/docs'));

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
    Route::get('saved', SavedRouteLibraryController::class)->name('routes.library');
    Route::post('routes/preview', PreviewRouteController::class)->name('routes.preview');
    Route::post('routes/build', BuildRouteController::class)->name('routes.build');
    Route::post('routes/multisport', PreviewMultisportController::class)->name('routes.multisport');
    Route::post('routes/snap', SnapPointController::class)->name('routes.snap');
    Route::post('routes/details', RouteDetailsController::class)->name('routes.details');
    Route::post('routes/stops', RouteStopsController::class)->name('routes.stops');
    Route::post('routes/fit', ExportFitCourseController::class)->name('routes.fit');
    Route::post('routes/import', ImportRouteController::class)->name('routes.import');
    Route::post('routes/import/redo', RedoImportedRouteController::class)->name('routes.import.redo');
    Route::post('routes/saved', StoreSavedRouteController::class)->name('routes.saved.store');
    Route::delete('routes/saved/{savedRoute}', DestroySavedRouteController::class)->name('routes.saved.destroy');
    Route::get('routes/saved/{savedRoute}/details', ShowSavedRouteDetailsController::class)->name('routes.saved.details');
});

require __DIR__.'/settings.php';
