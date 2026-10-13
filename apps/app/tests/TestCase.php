<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Routing\Details\RouteDetailsSource;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Fakes\UnavailableRouteDetails;

abstract class TestCase extends BaseTestCase
{
    /**
     * A test never asks the public map servers for a climb or a stop. A
     * test that needs details binds its own source.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->app?->instance(RouteDetailsSource::class, new UnavailableRouteDetails);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
