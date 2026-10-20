<?php

declare(strict_types=1);

use App\Account\ConnectionProvider;
use App\Account\RouteService;
use App\Domain\Routing\Sport;
use App\Domain\Usage\UsageMetric;
use App\Http\Controllers\Auth\ConnectionCallbackController;
use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Auth\FinishSignUpController;
use App\Http\Controllers\Auth\PendingConnectionController;
use App\Http\Controllers\Auth\SignInOptionsController;
use App\Http\Controllers\Auth\SignUpLinkController;
use App\Http\Controllers\Auth\StartConnectionController;
use App\Http\Controllers\BuildRouteController;
use App\Http\Controllers\DestroySavedRouteController;
use App\Http\Controllers\ExportFitCourseController;
use App\Http\Controllers\ExportSharedCourseController;
use App\Http\Controllers\ImportRouteController;
use App\Http\Controllers\NamePlaceController;
use App\Http\Controllers\PreviewMultisportController;
use App\Http\Controllers\PreviewRouteController;
use App\Http\Controllers\RedoImportedRouteController;
use App\Http\Controllers\RouteDetailsController;
use App\Http\Controllers\RouteStopsController;
use App\Http\Controllers\SavedRouteLibraryController;
use App\Http\Controllers\SearchPlacesController;
use App\Http\Controllers\SendRouteToServiceController;
use App\Http\Controllers\ShowSavedRouteDetailsController;
use App\Http\Controllers\ShowSharedRouteController;
use App\Http\Controllers\ShowSharedRouteEmbedController;
use App\Http\Controllers\ShowSharedRouteImageController;
use App\Http\Controllers\ShowStatusController;
use App\Http\Controllers\SnapPointController;
use App\Http\Controllers\StoreRouteImageController;
use App\Http\Controllers\StoreSavedRouteController;
use App\Http\Middleware\CountUsage;
use App\Http\Middleware\KeepOutOfSearch;
use App\Queries\Status\ServiceStatusQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('login/demo/{rider}', DemoLoginController::class)
    ->middleware('guest')
    ->name('login.demo');

// Logging in, or opening an account, with an account at another service.
Route::middleware('guest')->group(function (): void {
    Route::get('login/options', SignInOptionsController::class)->name('login.options');
    Route::get('auth/{provider}/redirect', [StartConnectionController::class, 'signIn'])
        ->whereIn('provider', ConnectionProvider::slugs())
        ->middleware('throttle:connections')
        ->name('connections.sign-in');
    Route::get('login/finish', [FinishSignUpController::class, 'show'])->name('connections.finish');
    Route::post('login/finish', [FinishSignUpController::class, 'store'])
        ->middleware('throttle:signup')
        ->name('connections.finish.store');
    Route::delete('login/finish', [FinishSignUpController::class, 'cancel'])->name('connections.finish.cancel');
    Route::post('login/finish/existing', [FinishSignUpController::class, 'existing'])->name('connections.finish.existing');
    // The link in the email. Opening it only shows the page; the button opens the account.
    Route::get('login/finish/{token}', [SignUpLinkController::class, 'show'])->name('connections.finish.link');
    Route::post('login/finish/{token}', [SignUpLinkController::class, 'store'])
        ->middleware('throttle:signup')
        ->name('connections.finish.open');
});

// A service waiting for the rider to log in. Once in, they say yes to connecting it, or not now.
Route::get('login/connection', [PendingConnectionController::class, 'show'])->middleware('auth')->name('connections.pending.show');
Route::post('login/connection', [PendingConnectionController::class, 'store'])->middleware('auth')->name('connections.pending.store');
Route::delete('login/connection', [PendingConnectionController::class, 'destroy'])->name('connections.pending.destroy');

// Every service sends the rider back here, signed in or not. Wahoo and Ride with GPS keep the callbacks they were
// first registered with.
Route::get('auth/{provider}/callback', ConnectionCallbackController::class)
    ->whereIn('provider', ConnectionProvider::slugs())
    ->middleware('throttle:connections')
    ->name('connections.callback');
Route::get('settings/services/{provider}/callback', ConnectionCallbackController::class)
    ->whereIn('provider', array_map(static fn (RouteService $routeService): string => $routeService->value, RouteService::cases()))
    ->middleware('throttle:connections')
    ->name('services.callback');

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::get('status', ShowStatusController::class)
    ->middleware('throttle:status')
    ->name('status');

Route::get('share/{savedRoute}', ShowSharedRouteController::class)
    ->middleware(['throttle:share', 'signed', KeepOutOfSearch::class])
    ->name('routes.share');
Route::get('share/{savedRoute}/embed', ShowSharedRouteEmbedController::class)
    ->middleware(['throttle:share', 'signed', KeepOutOfSearch::class])
    ->name('routes.share.embed');
Route::get('share/{savedRoute}/image', ShowSharedRouteImageController::class)
    ->middleware(['throttle:share', 'signed', KeepOutOfSearch::class])
    ->name('routes.share.image');
Route::get('share/{savedRoute}/gpx', [ExportSharedCourseController::class, 'gpx'])
    ->middleware(['throttle:share', 'signed', KeepOutOfSearch::class, CountUsage::as(UsageMetric::SharedDownloads)])
    ->name('routes.share.gpx');
Route::get('share/{savedRoute}/fit', [ExportSharedCourseController::class, 'fit'])
    ->middleware(['throttle:share', 'signed', KeepOutOfSearch::class, CountUsage::as(UsageMetric::SharedDownloads)])
    ->name('routes.share.fit');
Route::get('share/{savedRoute}/fit/{leg}', [ExportSharedCourseController::class, 'fitLeg'])
    ->whereNumber('leg')
    ->middleware(['throttle:share', 'signed', KeepOutOfSearch::class, CountUsage::as(UsageMetric::SharedDownloads)])
    ->name('routes.share.fit.leg');
Route::get('share/{savedRoute}/gpx/{leg}', [ExportSharedCourseController::class, 'gpxLeg'])
    ->whereNumber('leg')
    ->middleware(['throttle:share', 'signed', KeepOutOfSearch::class, CountUsage::as(UsageMetric::SharedDownloads)])
    ->name('routes.share.gpx.leg');

Route::get('docs/public', fn (): RedirectResponse => redirect()->to('/docs'));
Route::get('docs/account', fn (): RedirectResponse => redirect()->to('/docs'));

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn (ServiceStatusQuery $serviceStatusQuery) => Inertia::render('dashboard', [
        'serviceNotice' => $serviceStatusQuery->banner(),
        'sportSurfaces' => Sport::defaultSurfaces(),
    ]))->name('dashboard');
    Route::get('saved', SavedRouteLibraryController::class)->name('routes.library');
    Route::post('routes/preview', PreviewRouteController::class)->middleware(['throttle:routing', CountUsage::as(UsageMetric::RoutePreviews)])->name('routes.preview');
    Route::post('routes/build', BuildRouteController::class)->middleware(['throttle:routing', CountUsage::as(UsageMetric::RouteBuilds)])->name('routes.build');
    Route::post('routes/multisport', PreviewMultisportController::class)->middleware(['throttle:routing', CountUsage::as(UsageMetric::MultisportPreviews)])->name('routes.multisport');
    Route::post('routes/snap', SnapPointController::class)->middleware(['throttle:map', CountUsage::as(UsageMetric::PointSnaps)])->name('routes.snap');
    Route::post('routes/place', NamePlaceController::class)->middleware(['throttle:places', CountUsage::as(UsageMetric::PlaceNames)])->name('routes.place');
    Route::post('routes/places', SearchPlacesController::class)->middleware(['throttle:place-search', CountUsage::as(UsageMetric::PlaceSearches)])->name('routes.places');
    Route::post('routes/details', RouteDetailsController::class)->middleware('throttle:map')->name('routes.details');
    Route::post('routes/stops', RouteStopsController::class)->middleware('throttle:map')->name('routes.stops');
    Route::post('routes/fit', ExportFitCourseController::class)->middleware(['throttle:map', CountUsage::as(UsageMetric::FitExports)])->name('routes.fit');
    Route::post('routes/import', ImportRouteController::class)->middleware(['throttle:map', CountUsage::as(UsageMetric::RouteImports)])->name('routes.import');
    Route::post('routes/import/redo', RedoImportedRouteController::class)->middleware('throttle:map')->name('routes.import.redo');
    Route::post('routes/saved', StoreSavedRouteController::class)->middleware(['throttle:saving', CountUsage::as(UsageMetric::RoutesSaved)])->name('routes.saved.store');
    Route::delete('routes/saved/{savedRoute}', DestroySavedRouteController::class)->middleware('throttle:saving')->name('routes.saved.destroy');
    Route::get('routes/saved/{savedRoute}/details', ShowSavedRouteDetailsController::class)->whereUuid('savedRoute')->name('routes.saved.details');
    Route::post('routes/saved/{savedRoute}/image', StoreRouteImageController::class)->whereUuid('savedRoute')->middleware('throttle:saving')->name('routes.saved.image');
    Route::post('routes/saved/{savedRoute}/send/{service}', SendRouteToServiceController::class)
        ->whereUuid('savedRoute')
        ->whereIn('service', array_map(static fn (RouteService $routeService): string => $routeService->value, RouteService::cases()))
        ->middleware(['throttle:20,1', CountUsage::as(UsageMetric::RouteSends)])
        ->name('routes.saved.send');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
