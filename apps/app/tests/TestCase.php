<?php

declare(strict_types=1);

namespace Tests;

use App\Account\ConnectionProvider;
use App\Domain\Routing\Details\RouteDetailsSource;
use GuzzleHttp\HandlerStack;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Fakes\UnavailableRouteDetails;

abstract class TestCase extends BaseTestCase
{
    /**
     * A test never asks the public map servers for a climb or a stop. A
     * test that needs details binds its own source. Laravel's HTTP client
     * throws on any request a test did not fake, as Saloon does in Pest.php,
     * and so does every sign-in service's Socialite client.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->app?->instance(RouteDetailsSource::class, new UnavailableRouteDetails);
        Http::preventStrayRequests();
        $this->preventSignInRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Socialite speaks Guzzle, not Saloon or Laravel's HTTP client, so neither guard above stops it. Each
     * service's client gets a handler that throws instead. A test fakes the service with Socialite::fake(), or
     * gives its provider a client of its own with setHttpClient().
     */
    private function preventSignInRequests(): void
    {
        foreach (ConnectionProvider::slugs() as $slug) {
            config(['services.'.$slug.'.guzzle.handler' => HandlerStack::create(static function (RequestInterface $request) use ($slug): never {
                throw new RuntimeException('A test reached the '.$slug.' sign-in at '.$request->getUri()->getHost().'. Fake it with Socialite::fake(), or give the provider a client with setHttpClient().');
            })]);
        }
    }
}
