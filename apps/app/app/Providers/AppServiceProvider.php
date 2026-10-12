<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\SessionRider;
use App\Bus\CommandBus;
use App\Commands\ChangePasswordCommand;
use App\Commands\DeleteAccountCommand;
use App\Commands\DeleteRouteCommand;
use App\Commands\DisconnectAppCommand;
use App\Commands\ExportPersonalDataCommand;
use App\Commands\OpenAccountCommand;
use App\Commands\SaveRouteCommand;
use App\Commands\UpdateProfileCommand;
use App\Commands\WelcomeRiderCommand;
use App\Contracts\Account\ConnectedAppRepository;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Bus\DispatchesCommands;
use App\Contracts\Mail\AccountMailer;
use App\Contracts\Routes\SavedRouteRepository;
use App\Domain\Routing\PlaceFinder;
use App\Domain\Routing\RoutingEngine;
use App\Handlers\ChangePasswordHandler;
use App\Handlers\DeleteAccountHandler;
use App\Handlers\DeleteRouteHandler;
use App\Handlers\DisconnectAppHandler;
use App\Handlers\ExportPersonalDataHandler;
use App\Handlers\OpenAccountHandler;
use App\Handlers\SaveRouteHandler;
use App\Handlers\UpdateProfileHandler;
use App\Handlers\WelcomeRiderHandler;
use App\Integrations\GraphHopper\GraphHopperConnector;
use App\Integrations\GraphHopper\GraphHopperRoutingEngine;
use App\Integrations\Nominatim\NominatimConnector;
use App\Integrations\Nominatim\NominatimPlaceFinder;
use App\Integrations\Osrm\OsrmConnector;
use App\Integrations\Osrm\OsrmRoutingEngine;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaRoutingEngine;
use App\Mail\TracewayAccountMailer;
use App\Models\PreventLazyLoading;
use App\Notifications\RiderEmailConfirmation;
use App\Repositories\EloquentConnectedAppRepository;
use App\Repositories\EloquentSavedRouteRepository;
use App\Repositories\EloquentUserRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
            DeleteRouteCommand::class => DeleteRouteHandler::class,
            DisconnectAppCommand::class => DisconnectAppHandler::class,
        ]);
        $this->app->instance(DispatchesCommands::class, $commandBus);
        $this->app->alias(DispatchesCommands::class, CommandBus::class);

        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(SavedRouteRepository::class, EloquentSavedRouteRepository::class);
        $this->app->bind(ConnectedAppRepository::class, EloquentConnectedAppRepository::class);
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

            return new ValhallaRoutingEngine(
                new ValhallaConnector(is_string($url) && $url !== '' ? $url : 'https://valhalla1.openstreetmap.de'),
                is_int($span) && $span >= 10_000 ? $span : 140_000,
            );
        });

        $this->app->bind(function (): PlaceFinder {
            $url = config('services.nominatim.url');

            return new NominatimPlaceFinder(new NominatimConnector(
                is_string($url) && $url !== '' ? $url : 'https://nominatim.openstreetmap.org',
            ));
        });

        $this->app->bind(function (): ValhallaConnector {
            $url = config('services.valhalla.url');

            return new ValhallaConnector(
                is_string($url) && $url !== '' ? $url : 'https://valhalla1.openstreetmap.de',
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAiTools();
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
