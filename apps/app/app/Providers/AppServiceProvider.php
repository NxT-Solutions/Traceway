<?php

namespace App\Providers;

use App\Domain\Routing\RoutingEngine;
use App\Integrations\GraphHopper\GraphHopperConnector;
use App\Integrations\GraphHopper\GraphHopperRoutingEngine;
use App\Integrations\Osrm\OsrmConnector;
use App\Integrations\Osrm\OsrmRoutingEngine;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaRoutingEngine;
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
        $this->app->bind(RoutingEngine::class, function (): RoutingEngine {
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

        $this->app->bind(ValhallaConnector::class, function (): ValhallaConnector {
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
