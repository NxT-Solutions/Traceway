<?php

declare(strict_types=1);

namespace App\Providers;

use App\Account\RouteService;
use App\Auth\SessionRider;
use App\Bus\CommandBus;
use App\Commands\ChangePasswordCommand;
use App\Commands\ClaimDevicePairingCommand;
use App\Commands\ConnectRouteServiceCommand;
use App\Commands\DeleteAccountCommand;
use App\Commands\DeleteRouteCommand;
use App\Commands\DisconnectAppCommand;
use App\Commands\DisconnectRouteServiceCommand;
use App\Commands\ExportPersonalDataCommand;
use App\Commands\LinkDeviceCommand;
use App\Commands\MarkAllReleasesReadCommand;
use App\Commands\MarkReleaseReadCommand;
use App\Commands\OpenAccountCommand;
use App\Commands\ReadRouteDetailsCommand;
use App\Commands\RevokeDeviceCommand;
use App\Commands\SaveRouteCommand;
use App\Commands\SendRouteToServiceCommand;
use App\Commands\StartDevicePairingCommand;
use App\Commands\StoreRouteImageCommand;
use App\Commands\SyncReleasesCommand;
use App\Commands\UpdateProfileCommand;
use App\Commands\WelcomeRiderCommand;
use App\Contracts\Account\ConnectedAppRepository;
use App\Contracts\Account\DeviceLinkRepository;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\RouteServiceRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Bus\DispatchesCommands;
use App\Contracts\Mail\AccountMailer;
use App\Contracts\Releases\ReleaseRepository;
use App\Contracts\Routes\RouteImageStore;
use App\Contracts\Routes\SavedRouteRepository;
use App\Domain\Courses\Services\CourseServices;
use App\Domain\Releases\ReleaseFeed;
use App\Domain\Routing\Details\RouteDetailer;
use App\Domain\Routing\Details\RouteDetailsSource;
use App\Domain\Routing\LineFollower;
use App\Domain\Routing\PlaceFinder;
use App\Domain\Routing\PlaceNamer;
use App\Domain\Routing\PlaceSearch;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\TracingLineFollower;
use App\Handlers\ChangePasswordHandler;
use App\Handlers\ClaimDevicePairingHandler;
use App\Handlers\ConnectRouteServiceHandler;
use App\Handlers\DeleteAccountHandler;
use App\Handlers\DeleteRouteHandler;
use App\Handlers\DisconnectAppHandler;
use App\Handlers\DisconnectRouteServiceHandler;
use App\Handlers\ExportPersonalDataHandler;
use App\Handlers\LinkDeviceHandler;
use App\Handlers\MarkAllReleasesReadHandler;
use App\Handlers\MarkReleaseReadHandler;
use App\Handlers\OpenAccountHandler;
use App\Handlers\ReadRouteDetailsHandler;
use App\Handlers\RevokeDeviceHandler;
use App\Handlers\SaveRouteHandler;
use App\Handlers\SendRouteToServiceHandler;
use App\Handlers\StartDevicePairingHandler;
use App\Handlers\StoreRouteImageHandler;
use App\Handlers\SyncReleasesHandler;
use App\Handlers\UpdateProfileHandler;
use App\Handlers\WelcomeRiderHandler;
use App\Integrations\GraphHopper\GraphHopperConnector;
use App\Integrations\GraphHopper\GraphHopperRoutingEngine;
use App\Integrations\Nominatim\NominatimConnector;
use App\Integrations\Nominatim\NominatimPlaceFinder;
use App\Integrations\Nominatim\NominatimPlaceNamer;
use App\Integrations\Nominatim\NominatimPlaceSearch;
use App\Integrations\OpenFreeMap\OpenFreeMapConnector;
use App\Integrations\OpenFreeMap\OpenFreeMapStopFinder;
use App\Integrations\Osrm\OsrmConnector;
use App\Integrations\Osrm\OsrmRoutingEngine;
use App\Integrations\ReleaseNotes\ReleaseNotesDirectory;
use App\Integrations\RideWithGps\RideWithGpsConnector;
use App\Integrations\RideWithGps\RideWithGpsCourseService;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaRoadDescriber;
use App\Integrations\Valhalla\ValhallaRoutingEngine;
use App\Integrations\Wahoo\WahooConnector;
use App\Integrations\Wahoo\WahooCourseService;
use App\Mail\TracewayAccountMailer;
use App\Models\PreventLazyLoading;
use App\Notifications\RiderEmailConfirmation;
use App\Repositories\DiskRouteImageStore;
use App\Repositories\EloquentConnectedAppRepository;
use App\Repositories\EloquentDeviceLinkRepository;
use App\Repositories\EloquentReleaseRepository;
use App\Repositories\EloquentRouteServiceRepository;
use App\Repositories\EloquentSavedRouteRepository;
use App\Repositories\EloquentUserRepository;
use App\Routes\CachedPlaceNamer;
use App\Routes\CachedPlaceSearch;
use App\Routes\CachedRouteDetails;
use App\Routes\PacedPlaceFinder;
use App\Routes\PlaceSearchPace;
use App\Routes\RelaxedPlaceSearch;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $commandBus = new CommandBus($this->app, [
            OpenAccountCommand::class => OpenAccountHandler::class,
            WelcomeRiderCommand::class => WelcomeRiderHandler::class,
            UpdateProfileCommand::class => UpdateProfileHandler::class,
            ChangePasswordCommand::class => ChangePasswordHandler::class,
            DeleteAccountCommand::class => DeleteAccountHandler::class,
            ExportPersonalDataCommand::class => ExportPersonalDataHandler::class,
            SaveRouteCommand::class => SaveRouteHandler::class,
            ReadRouteDetailsCommand::class => ReadRouteDetailsHandler::class,
            DeleteRouteCommand::class => DeleteRouteHandler::class,
            StoreRouteImageCommand::class => StoreRouteImageHandler::class,
            DisconnectAppCommand::class => DisconnectAppHandler::class,
            StartDevicePairingCommand::class => StartDevicePairingHandler::class,
            ClaimDevicePairingCommand::class => ClaimDevicePairingHandler::class,
            LinkDeviceCommand::class => LinkDeviceHandler::class,
            RevokeDeviceCommand::class => RevokeDeviceHandler::class,
            ConnectRouteServiceCommand::class => ConnectRouteServiceHandler::class,
            DisconnectRouteServiceCommand::class => DisconnectRouteServiceHandler::class,
            SendRouteToServiceCommand::class => SendRouteToServiceHandler::class,
            SyncReleasesCommand::class => SyncReleasesHandler::class,
            MarkReleaseReadCommand::class => MarkReleaseReadHandler::class,
            MarkAllReleasesReadCommand::class => MarkAllReleasesReadHandler::class,
        ]);
        $this->app->instance(DispatchesCommands::class, $commandBus);
        $this->app->alias(DispatchesCommands::class, CommandBus::class);

        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(SavedRouteRepository::class, EloquentSavedRouteRepository::class);
        $this->app->bind(RouteImageStore::class, fn (): DiskRouteImageStore => new DiskRouteImageStore(Storage::disk('local')));
        $this->app->bind(ConnectedAppRepository::class, EloquentConnectedAppRepository::class);
        $this->app->bind(DeviceLinkRepository::class, EloquentDeviceLinkRepository::class);
        $this->app->bind(RouteServiceRepository::class, EloquentRouteServiceRepository::class);
        $this->app->bind(ReleaseRepository::class, EloquentReleaseRepository::class);
        $this->app->bind(CurrentRider::class, SessionRider::class);
        $this->app->bind(AccountMailer::class, TracewayAccountMailer::class);
        $this->app->bind(EmailConfirmation::class, RiderEmailConfirmation::class);

        $this->app->bind(function (): RoutingEngine {
            $driver = config('services.router');

            if ($driver === 'graphhopper') {
                $url = config('services.graphhopper.url');

                return new GraphHopperRoutingEngine(new GraphHopperConnector(
                    is_string($url) && $url !== '' ? $url : 'http://graphhopper:8989',
                ));
            }

            if ($driver === 'osrm') {
                $url = config('services.osrm.url');

                return new OsrmRoutingEngine(new OsrmConnector(
                    is_string($url) && $url !== '' ? $url : 'https://router.project-osrm.org',
                ));
            }

            $url = config('services.valhalla.url');
            $span = config('services.valhalla.max_request_metres');
            $points = config('services.valhalla.max_request_points');

            return new ValhallaRoutingEngine(
                new ValhallaConnector(is_string($url) && $url !== '' ? $url : 'https://valhalla1.openstreetmap.de'),
                is_int($span) && $span >= 10_000 ? $span : 140_000,
                is_int($points) && $points >= 2 ? $points : 10,
            );
        });

        $this->app->bind(fn (): CourseServices => $this->courseServices());

        $this->app->bind(fn (): LineFollower => $this->lineFollower($this->app->make(RoutingEngine::class)));

        $this->app->bind(fn (): PlaceFinder => new PacedPlaceFinder(
            new NominatimPlaceFinder($this->nominatimConnector()),
            new PlaceSearchPace($this->app->make(CacheRepository::class)),
        ));

        $this->app->bind(fn (): PlaceNamer => new CachedPlaceNamer(
            new NominatimPlaceNamer($this->nominatimConnector()),
            $this->app->make(CacheRepository::class),
            new PlaceSearchPace($this->app->make(CacheRepository::class)),
        ));

        $this->app->bind(fn (): PlaceSearch => new RelaxedPlaceSearch(new CachedPlaceSearch(
            new NominatimPlaceSearch($this->nominatimConnector()),
            $this->app->make(CacheRepository::class),
            new PlaceSearchPace($this->app->make(CacheRepository::class)),
        )));

        $this->app->bind(ReleaseFeed::class, fn (): ReleaseNotesDirectory => new ReleaseNotesDirectory(resource_path('releases')));
        $this->app->bind(ReleaseNotesDirectory::class, fn (): ReleaseNotesDirectory => new ReleaseNotesDirectory(resource_path('releases')));

        $this->app->bind(function (): RouteDetailsSource {
            $valhalla = config('services.valhalla.url');
            $tiles = config('services.openfreemap.url');

            return new CachedRouteDetails(
                new RouteDetailer(
                    new ValhallaRoadDescriber(new ValhallaConnector(is_string($valhalla) && $valhalla !== '' ? $valhalla : 'https://valhalla1.openstreetmap.de')),
                    new OpenFreeMapStopFinder(new OpenFreeMapConnector(is_string($tiles) && $tiles !== '' ? $tiles : 'https://tiles.openfreemap.org')),
                ),
                $this->app->make(CacheRepository::class),
            );
        });

        $this->app->bind(function (): ValhallaConnector {
            $url = config('services.valhalla.url');

            return new ValhallaConnector(
                is_string($url) && $url !== '' ? $url : 'https://valhalla1.openstreetmap.de',
            );
        });
    }

    private function nominatimConnector(): NominatimConnector
    {
        $url = config('services.nominatim.url');

        return new NominatimConnector(is_string($url) && $url !== '' ? $url : 'https://nominatim.openstreetmap.org');
    }

    /**
     * An engine that can snap a line and hold a heading follows lines
     * itself. Any other engine follows them through points.
     */
    private function lineFollower(RoutingEngine $routingEngine): LineFollower
    {
        return $routingEngine instanceof LineFollower ? $routingEngine : new TracingLineFollower($routingEngine);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureAiTools();
    }

    /**
     * A Garmin asks for its routes over the phone. Pairing is tighter, since a code is only six characters.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('device', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('device-pairing', fn (Request $request): Limit => Limit::perMinute(6)->by($request->ip()));
        // Each of these asks the map servers several times. A rider redraws
        // often, so the limit is generous, but one account cannot flood them.
        RateLimiter::for('map', function (Request $request): Limit {
            $id = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(60)->by('map:'.(is_scalar($id) ? (string) $id : (string) $request->ip()));
        });
        // Naming a pin has its own bucket, so a rider who adds many points
        // still gets the climb chart and the course file.
        RateLimiter::for('places', function (Request $request): Limit {
            $id = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(60)->by('places:'.(is_scalar($id) ? (string) $id : (string) $request->ip()));
        });
        // The place search's own pace keeps Traceway at one request a second;
        // this only stops one rider taking every turn. A search can cost two
        // or three requests with its retries, so a rider gets plenty.
        RateLimiter::for('place-search', function (Request $request): Limit {
            $id = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(30)
                ->by('place-search:'.(is_scalar($id) ? (string) $id : (string) $request->ip()))
                ->response(static function (Request $request, array $headers): JsonResponse {
                    $seconds = $headers['Retry-After'] ?? 60;

                    return new JsonResponse([
                        'message' => sprintf(
                            'You have used all 30 searches for this minute. You can search again in %d seconds.',
                            is_numeric($seconds) ? (int) $seconds : 60,
                        ),
                    ], 429, $headers);
                });
        });
    }

    /**
     * AI tools sign in with OAuth, ask the person on a Traceway page, and
     * share the public routing engine with everyone else, so they go slowly.
     */
    protected function configureAiTools(): void
    {
        Passport::authorizationView('mcp.authorize');
        Passport::tokensExpireIn(CarbonInterval::days(30));
        Passport::refreshTokensExpireIn(CarbonInterval::days(90));

        RateLimiter::for('mcp', function (Request $request): array {
            $id = $request->user()?->getAuthIdentifier();
            $caller = is_scalar($id) ? (string) $id : (string) $request->ip();

            return [
                Limit::perMinute(20)->by('mcp:'.$caller),
                Limit::perDay(300)->by('mcp-day:'.$caller),
            ];
        });
    }

    /**
     * The services a rider can send routes to. A service without a client id and secret is left out,
     * so a fresh checkout shows none.
     */
    private function courseServices(): CourseServices
    {
        $services = [];
        $wahoo = $this->credentials('wahoo');
        $rideWithGps = $this->credentials('ride_with_gps');

        if ($wahoo !== null) {
            $services[RouteService::Wahoo->value] = new WahooCourseService(new WahooConnector(
                $wahoo['client_id'],
                $wahoo['client_secret'],
                url('/settings/services/wahoo/callback'),
                $wahoo['url'] ?? 'https://api.wahooligan.com',
            ));
        }

        if ($rideWithGps !== null) {
            $services[RouteService::RideWithGps->value] = new RideWithGpsCourseService(new RideWithGpsConnector(
                $rideWithGps['client_id'],
                $rideWithGps['client_secret'],
                url('/settings/services/ride_with_gps/callback'),
                $rideWithGps['api_key'] ?? null,
                $rideWithGps['url'] ?? 'https://ridewithgps.com',
            ));
        }

        return new CourseServices($services);
    }

    /**
     * @return array{client_id: string, client_secret: string, url?: string, api_key?: string}|null
     */
    private function credentials(string $service): ?array
    {
        $config = config('services.'.$service);

        if (! is_array($config)) {
            return null;
        }

        $clientId = $config['client_id'] ?? null;
        $clientSecret = $config['client_secret'] ?? null;

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            return null;
        }

        $credentials = ['client_id' => $clientId, 'client_secret' => $clientSecret];

        foreach (['url', 'api_key'] as $key) {
            if (is_string($config[$key] ?? null) && $config[$key] !== '') {
                $credentials[$key] = $config[$key];
            }
        }

        return $credentials;
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        PreventLazyLoading::outsideProduction();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
