<?php

declare(strict_types=1);

namespace App\Providers;

use App\Account\Role;
use App\Account\RouteService;
use App\Auth\GateAdminAccess;
use App\Auth\SessionImpersonator;
use App\Auth\SessionRider;
use App\Bus\CommandBus;
use App\Commands\AdminAssignRoleCommand;
use App\Commands\AdminCreateUserCommand;
use App\Commands\AdminDeleteUserCommand;
use App\Commands\AdminEndSessionsCommand;
use App\Commands\AdminResetTwoFactorCommand;
use App\Commands\AdminUpdateUserCommand;
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
use App\Commands\PostStatusNoticeCommand;
use App\Commands\ReadRouteDetailsCommand;
use App\Commands\RecordServiceChecksCommand;
use App\Commands\RecordTrafficSnapshotCommand;
use App\Commands\RecordUsageSnapshotCommand;
use App\Commands\ResolveStatusNoticeCommand;
use App\Commands\RevokeDeviceCommand;
use App\Commands\SaveRouteCommand;
use App\Commands\SendRouteToServiceCommand;
use App\Commands\StartDevicePairingCommand;
use App\Commands\StartImpersonationCommand;
use App\Commands\StopImpersonationCommand;
use App\Commands\StoreRouteImageCommand;
use App\Commands\SyncReleasesCommand;
use App\Commands\UpdateProfileCommand;
use App\Commands\WelcomeRiderCommand;
use App\Contracts\Account\AdminLog;
use App\Contracts\Account\ConnectedAppRepository;
use App\Contracts\Account\DeviceLinkRepository;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\PasswordLink;
use App\Contracts\Account\RouteServiceRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\AdminAccess;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Auth\Impersonator;
use App\Contracts\Bus\DispatchesCommands;
use App\Contracts\Mail\AccountMailer;
use App\Contracts\Releases\ReleaseRepository;
use App\Contracts\Routes\RouteImageStore;
use App\Contracts\Routes\SavedRouteRepository;
use App\Contracts\Status\StatusRepository;
use App\Contracts\Usage\UsageCounter;
use App\Domain\Access\HumanCheck;
use App\Domain\Access\NoHumanCheck;
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
use App\Domain\Status\ServiceProbes;
use App\Domain\Status\StatusComponent;
use App\Domain\Traffic\TrafficInsights;
use App\Domain\Traffic\TrafficSnapshots;
use App\Domain\Traffic\TrafficSource;
use App\Domain\Traffic\UnconnectedTrafficInsights;
use App\Domain\Traffic\UnconnectedTrafficSource;
use App\Handlers\AdminAssignRoleHandler;
use App\Handlers\AdminCreateUserHandler;
use App\Handlers\AdminDeleteUserHandler;
use App\Handlers\AdminEndSessionsHandler;
use App\Handlers\AdminResetTwoFactorHandler;
use App\Handlers\AdminUpdateUserHandler;
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
use App\Handlers\PostStatusNoticeHandler;
use App\Handlers\ReadRouteDetailsHandler;
use App\Handlers\RecordServiceChecksHandler;
use App\Handlers\RecordTrafficSnapshotHandler;
use App\Handlers\RecordUsageSnapshotHandler;
use App\Handlers\ResolveStatusNoticeHandler;
use App\Handlers\RevokeDeviceHandler;
use App\Handlers\SaveRouteHandler;
use App\Handlers\SendRouteToServiceHandler;
use App\Handlers\StartDevicePairingHandler;
use App\Handlers\StartImpersonationHandler;
use App\Handlers\StopImpersonationHandler;
use App\Handlers\StoreRouteImageHandler;
use App\Handlers\SyncReleasesHandler;
use App\Handlers\UpdateProfileHandler;
use App\Handlers\WelcomeRiderHandler;
use App\Integrations\Cloudflare\CloudflareAnalyticsConnector;
use App\Integrations\Cloudflare\CloudflareGraphql;
use App\Integrations\Cloudflare\CloudflareInsights;
use App\Integrations\Cloudflare\CloudflareTrafficSource;
use App\Integrations\Cloudflare\TurnstileConnector;
use App\Integrations\Cloudflare\TurnstileHumanCheck;
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
use App\Integrations\Status\ConnectIqStoreProbe;
use App\Integrations\Status\HttpServiceProbe;
use App\Integrations\Status\StatusConnector;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaRoadDescriber;
use App\Integrations\Valhalla\ValhallaRoutingEngine;
use App\Integrations\Wahoo\WahooConnector;
use App\Integrations\Wahoo\WahooCourseService;
use App\Mail\TracewayAccountMailer;
use App\Models\PreventLazyLoading;
use App\Models\User;
use App\Notifications\RiderEmailConfirmation;
use App\Notifications\RiderPasswordLink;
use App\Queries\Status\PlannerProbe;
use App\Repositories\DatabaseAdminLog;
use App\Repositories\DatabaseStatusRepository;
use App\Repositories\DatabaseTrafficSnapshots;
use App\Repositories\DatabaseUsageCounter;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
            RecordServiceChecksCommand::class => RecordServiceChecksHandler::class,
            PostStatusNoticeCommand::class => PostStatusNoticeHandler::class,
            ResolveStatusNoticeCommand::class => ResolveStatusNoticeHandler::class,
            RecordUsageSnapshotCommand::class => RecordUsageSnapshotHandler::class,
            RecordTrafficSnapshotCommand::class => RecordTrafficSnapshotHandler::class,
            StartImpersonationCommand::class => StartImpersonationHandler::class,
            StopImpersonationCommand::class => StopImpersonationHandler::class,
            AdminCreateUserCommand::class => AdminCreateUserHandler::class,
            AdminUpdateUserCommand::class => AdminUpdateUserHandler::class,
            AdminAssignRoleCommand::class => AdminAssignRoleHandler::class,
            AdminDeleteUserCommand::class => AdminDeleteUserHandler::class,
            AdminResetTwoFactorCommand::class => AdminResetTwoFactorHandler::class,
            AdminEndSessionsCommand::class => AdminEndSessionsHandler::class,
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
        $this->app->bind(StatusRepository::class, DatabaseStatusRepository::class);
        $this->app->bind(UsageCounter::class, DatabaseUsageCounter::class);
        $this->app->bind(TrafficSnapshots::class, DatabaseTrafficSnapshots::class);
        $this->app->bind(AdminLog::class, DatabaseAdminLog::class);
        $this->app->bind(fn (): ServiceProbes => $this->serviceProbes());
        $this->app->bind(CurrentRider::class, SessionRider::class);
        $this->app->bind(AdminAccess::class, GateAdminAccess::class);
        $this->app->bind(Impersonator::class, SessionImpersonator::class);
        $this->app->bind(AccountMailer::class, TracewayAccountMailer::class);
        $this->app->bind(EmailConfirmation::class, RiderEmailConfirmation::class);
        $this->app->bind(PasswordLink::class, RiderPasswordLink::class);

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

        // Turnstile asks Cloudflare on every sign-up. With no keys, as in
        // development and tests, the forms keep only their rate limits.
        $this->app->bind(function (): HumanCheck {
            $siteKey = config('services.turnstile.site_key');
            $secret = config('services.turnstile.secret_key');

            return is_string($siteKey) && $siteKey !== '' && is_string($secret) && $secret !== ''
                ? new TurnstileHumanCheck($this->app->make(TurnstileConnector::class), $secret)
                : new NoHumanCheck;
        });

        // The Traffic page asks Cloudflare with a read-only Analytics token. Without one, as in development and
        // tests, the page says it is not connected.
        $this->app->bind(function (): TrafficSource {
            $token = $this->configured('cloudflare_analytics.token', '');
            $zoneId = $this->configured('cloudflare_analytics.zone_id', '');
            $accountId = $this->configured('cloudflare_analytics.account_id', '');
            $siteTag = $this->configured('cloudflare_analytics.site_tag', '');

            if ($token === '' || ($zoneId === '' && ($accountId === '' || $siteTag === ''))) {
                return new UnconnectedTrafficSource;
            }

            return new CloudflareTrafficSource(
                new CloudflareAnalyticsConnector($token),
                $zoneId !== '' ? $zoneId : null,
                $accountId !== '' ? $accountId : null,
                $siteTag !== '' ? $siteTag : null,
            );
        });

        // The rest of the Traffic pages ask Cloudflare with the same token, one part at a time.
        $this->app->bind(function (): TrafficInsights {
            $token = $this->configured('cloudflare_analytics.token', '');
            $zoneId = $this->configured('cloudflare_analytics.zone_id', '');
            $accountId = $this->configured('cloudflare_analytics.account_id', '');

            if ($token === '' || ($zoneId === '' && $accountId === '')) {
                return new UnconnectedTrafficInsights;
            }

            $siteTag = $this->configured('cloudflare_analytics.site_tag', '');
            $bucket = $this->configured('cloudflare_analytics.backup_bucket', '');
            $turnstile = $this->configured('turnstile.site_key', '');

            return new CloudflareInsights(
                new CloudflareGraphql(new CloudflareAnalyticsConnector($token)),
                $zoneId !== '' ? $zoneId : null,
                $accountId !== '' ? $accountId : null,
                $siteTag !== '' ? $siteTag : null,
                $bucket !== '' ? $bucket : null,
                $turnstile !== '' ? $turnstile : null,
            );
        });

        $this->app->bind(function (): ValhallaConnector {
            $url = config('services.valhalla.url');

            return new ValhallaConnector(
                is_string($url) && $url !== '' ? $url : 'https://valhalla1.openstreetmap.de',
            );
        });
    }

    /**
     * What the status page checks: Traceway itself, the map servers the planner is set up to
     * use, the apps a rider can send to once they are set up, and the Garmin store listing.
     */
    private function serviceProbes(): ServiceProbes
    {
        $router = config('services.router');
        $routing = match ($router) {
            'graphhopper' => [$this->configured('graphhopper.url', 'http://graphhopper:8989'), '/health'],
            'osrm' => [$this->configured('osrm.url', 'https://router.project-osrm.org'), '/nearest/v1/driving/13.38886,52.517037'],
            default => [$this->configured('valhalla.url', 'https://valhalla1.openstreetmap.de'), '/status'],
        };

        $probes = [
            new PlannerProbe,
            new HttpServiceProbe(StatusComponent::Routing, new StatusConnector($routing[0]), $routing[1]),
            new HttpServiceProbe(StatusComponent::Places, new StatusConnector($this->configured('nominatim.url', 'https://nominatim.openstreetmap.org')), '/status'),
            new HttpServiceProbe(StatusComponent::Tiles, new StatusConnector($this->configured('openfreemap.url', 'https://tiles.openfreemap.org')), '/planet'),
        ];

        $rideWithGps = $this->credentials('ride_with_gps');

        if ($rideWithGps !== null) {
            $probes[] = new HttpServiceProbe(StatusComponent::RideWithGps, new StatusConnector($rideWithGps['url'] ?? 'https://ridewithgps.com'), '/robots.txt');
        }

        $wahoo = $this->credentials('wahoo');

        if ($wahoo !== null) {
            $probes[] = new HttpServiceProbe(StatusComponent::Wahoo, new StatusConnector($wahoo['url'] ?? 'https://api.wahooligan.com'), '/');
        }

        $storeId = config('services.connect_iq.store_id');

        if (is_string($storeId) && $storeId !== '') {
            $probes[] = new ConnectIqStoreProbe(new StatusConnector($this->configured('connect_iq.url', 'https://apps.garmin.com')), $storeId);
        }

        return new ServiceProbes($probes);
    }

    /**
     * A URL from config/services.php, or the default when it is empty.
     */
    private function configured(string $key, string $default): string
    {
        $value = config('services.'.$key);

        return is_string($value) && $value !== '' ? $value : $default;
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
        $this->configureRoles();
    }

    /**
     * A Super Admin passes every permission check. Everyone else is asked about the permission itself, so it
     * answers null, never false, and leaves the decision to the next check.
     */
    protected function configureRoles(): void
    {
        Gate::before(static fn (mixed $user): ?bool => $user instanceof User && $user->hasRole(Role::SuperAdmin->value) ? true : null);
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
        RateLimiter::for('map', fn (Request $request): Limit => Limit::perMinute(60)->by('map:'.$this->caller($request)));
        // Drawing a route asks the routing engine for the whole line, and a
        // loop asks it several times. A rider draws when they ask for it, not
        // on every tap, so thirty a minute leaves plenty.
        RateLimiter::for('routing', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('routing:'.$this->caller($request))
            ->response(fn (Request $request, array $headers): JsonResponse => $this->slowDown(
                'You have drawn a lot of routes this minute. You can draw again in %d seconds.',
                $headers,
            )));
        // Saving writes the route and its picture to the database.
        RateLimiter::for('saving', fn (Request $request): Limit => Limit::perMinute(30)->by('saving:'.$this->caller($request)));
        // A shared link is public. Anyone with it may download the course, but
        // one address cannot pull it over and over.
        RateLimiter::for('share', fn (Request $request): Limit => Limit::perMinute(60)->by('share:'.$request->ip()));
        // The status page is public and read from a cache, but one address
        // cannot reload it without end.
        RateLimiter::for('status', fn (Request $request): Limit => Limit::perMinute(60)->by('status:'.$request->ip()));
        // A copy of all your data is built in one go.
        RateLimiter::for('export', fn (Request $request): Limit => Limit::perMinute(3)->by('export:'.$this->caller($request)));
        // Each sign-up and each reset link sends an email, so a script must
        // not fill these forms by the thousand.
        RateLimiter::for('signup', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('signup:'.$request->ip())
            ->response(static function (Request $request, array $headers): RedirectResponse {
                $seconds = $headers['Retry-After'] ?? 60;

                return back()->withInput($request->except('password', 'password_confirmation'))->withErrors([
                    'email' => sprintf(
                        'There were too many tries from here. Please wait %d seconds, then try again.',
                        is_numeric($seconds) ? (int) $seconds : 60,
                    ),
                ]);
            }));
        // Naming a pin has its own bucket, so a rider who adds many points
        // still gets the climb chart and the course file.
        RateLimiter::for('places', fn (Request $request): Limit => Limit::perMinute(60)->by('places:'.$this->caller($request)));
        // The place search's own pace keeps Traceway at one request a second;
        // this only stops one rider taking every turn. A search can cost two
        // or three requests with its retries, so a rider gets plenty.
        RateLimiter::for('place-search', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('place-search:'.$this->caller($request))
            ->response(fn (Request $request, array $headers): JsonResponse => $this->slowDown(
                'You have used all 30 searches for this minute. You can search again in %d seconds.',
                $headers,
            )));
    }

    /**
     * The signed-in rider, or the address of a visitor.
     */
    private function caller(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : (string) $request->ip();
    }

    /**
     * A 429 the planner shows as it is, with the seconds left to wait.
     *
     * @param  array<mixed>  $headers  the limiter's Retry-After and limit headers
     */
    private function slowDown(string $message, array $headers): JsonResponse
    {
        $seconds = $headers['Retry-After'] ?? 60;

        return new JsonResponse([
            'message' => sprintf($message, is_numeric($seconds) ? (int) $seconds : 60),
        ], 429, $headers);
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

        // An AI tool registers itself once, before the person signs in. Each
        // registration is a row, so one address cannot add them without end.
        // The sign-in metadata in the same group is not counted.
        RateLimiter::for('oauth-register', fn (Request $request): Limit => $request->isMethod('POST')
            ? Limit::perMinute(30)->by('oauth-register:'.$request->ip())
            : Limit::none());

        RateLimiter::for('mcp', function (Request $request): array {
            $caller = $this->caller($request);

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
