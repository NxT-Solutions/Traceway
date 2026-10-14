<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\SessionRider;
use App\Bus\CommandBus;
use App\Commands\ChangePasswordCommand;
use App\Commands\ClaimDevicePairingCommand;
use App\Commands\DeleteAccountCommand;
use App\Commands\DeleteRouteCommand;
use App\Commands\DisconnectAppCommand;
use App\Commands\ExportPersonalDataCommand;
use App\Commands\LinkDeviceCommand;
use App\Commands\OpenAccountCommand;
use App\Commands\ReadRouteDetailsCommand;
use App\Commands\RevokeDeviceCommand;
use App\Commands\SaveRouteCommand;
use App\Commands\StartDevicePairingCommand;
use App\Commands\StoreRouteImageCommand;
use App\Commands\UpdateProfileCommand;
use App\Commands\WelcomeRiderCommand;
use App\Contracts\Account\ConnectedAppRepository;
use App\Contracts\Account\DeviceLinkRepository;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Bus\DispatchesCommands;
use App\Contracts\Mail\AccountMailer;
use App\Contracts\Routes\RouteImageStore;
use App\Contracts\Routes\SavedRouteRepository;
use App\Domain\Routing\Details\RouteDetailer;
use App\Domain\Routing\Details\RouteDetailsSource;
use App\Domain\Routing\LineFollower;
use App\Domain\Routing\PlaceFinder;
use App\Domain\Routing\PlaceNamer;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\TracingLineFollower;
use App\Handlers\ChangePasswordHandler;
use App\Handlers\ClaimDevicePairingHandler;
use App\Handlers\DeleteAccountHandler;
use App\Handlers\DeleteRouteHandler;
use App\Handlers\DisconnectAppHandler;
use App\Handlers\ExportPersonalDataHandler;
use App\Handlers\LinkDeviceHandler;
use App\Handlers\OpenAccountHandler;
use App\Handlers\ReadRouteDetailsHandler;
use App\Handlers\RevokeDeviceHandler;
use App\Handlers\SaveRouteHandler;
use App\Handlers\StartDevicePairingHandler;
use App\Handlers\StoreRouteImageHandler;
use App\Handlers\UpdateProfileHandler;
use App\Handlers\WelcomeRiderHandler;
use App\Integrations\GraphHopper\GraphHopperConnector;
use App\Integrations\GraphHopper\GraphHopperRoutingEngine;
use App\Integrations\Nominatim\NominatimConnector;
use App\Integrations\Nominatim\NominatimPlaceFinder;
use App\Integrations\Nominatim\NominatimPlaceNamer;
use App\Integrations\OpenFreeMap\OpenFreeMapConnector;
use App\Integrations\OpenFreeMap\OpenFreeMapStopFinder;
use App\Integrations\Osrm\OsrmConnector;
use App\Integrations\Osrm\OsrmRoutingEngine;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaRoadDescriber;
use App\Integrations\Valhalla\ValhallaRoutingEngine;
use App\Mail\TracewayAccountMailer;
use App\Models\PreventLazyLoading;
use App\Notifications\RiderEmailConfirmation;
use App\Repositories\DiskRouteImageStore;
use App\Repositories\EloquentConnectedAppRepository;
use App\Repositories\EloquentDeviceLinkRepository;
use App\Repositories\EloquentSavedRouteRepository;
use App\Repositories\EloquentUserRepository;
use App\Routes\CachedPlaceNamer;
use App\Routes\CachedRouteDetails;
use App\Routes\PacedPlaceFinder;
use App\Routes\PlaceSearchPace;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
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
        ]);
        $this->app->instance(DispatchesCommands::class, $commandBus);
        $this->app->alias(DispatchesCommands::class, CommandBus::class);

        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(SavedRouteRepository::class, EloquentSavedRouteRepository::class);
        $this->app->bind(RouteImageStore::class, fn (): DiskRouteImageStore => new DiskRouteImageStore(Storage::disk('local')));
        $this->app->bind(ConnectedAppRepository::class, EloquentConnectedAppRepository::class);
        $this->app->bind(DeviceLinkRepository::class, EloquentDeviceLinkRepository::class);
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
