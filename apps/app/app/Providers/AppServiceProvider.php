<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\SessionRider;
use App\Bus\CommandBus;
use App\Commands\ChangePasswordCommand;
use App\Commands\DeleteAccountCommand;
use App\Commands\DeleteRouteCommand;
use App\Commands\ExportPersonalDataCommand;
use App\Commands\OpenAccountCommand;
use App\Commands\SaveRouteCommand;
use App\Commands\UpdateProfileCommand;
use App\Commands\WelcomeRiderCommand;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Bus\DispatchesCommands;
use App\Contracts\Mail\AccountMailer;
use App\Contracts\Routes\SavedRouteRepository;
use App\Domain\Routing\RoutingEngine;
use App\Handlers\ChangePasswordHandler;
use App\Handlers\DeleteAccountHandler;
use App\Handlers\DeleteRouteHandler;
use App\Handlers\ExportPersonalDataHandler;
use App\Handlers\OpenAccountHandler;
use App\Handlers\SaveRouteHandler;
use App\Handlers\UpdateProfileHandler;
use App\Handlers\WelcomeRiderHandler;
use App\Integrations\GraphHopper\GraphHopperConnector;
use App\Integrations\GraphHopper\GraphHopperRoutingEngine;
use App\Integrations\Osrm\OsrmConnector;
use App\Integrations\Osrm\OsrmRoutingEngine;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaRoutingEngine;
use App\Mail\TracewayAccountMailer;
use App\Models\PreventLazyLoading;
use App\Notifications\RiderEmailConfirmation;
use App\Repositories\EloquentSavedRouteRepository;
use App\Repositories\EloquentUserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        ]);
        $this->app->instance(DispatchesCommands::class, $commandBus);
        $this->app->alias(DispatchesCommands::class, CommandBus::class);

        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(SavedRouteRepository::class, EloquentSavedRouteRepository::class);
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

            return new ValhallaRoutingEngine(new ValhallaConnector(
                is_string($url) && $url !== '' ? $url : 'https://valhalla1.openstreetmap.de',
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
