<?php

declare(strict_types=1);

use Saloon\Config;
use Saloon\MockConfig;
use Tests\TestCase;

/*
| Pest 5. Feature tests boot the Laravel application. Unit tests that only
| exercise domain values stay on PHPUnit's case so they do not open a
| database. Architecture tests do the same.
|
| RefreshDatabase is applied per file, not globally, so a test that does not
| touch the database does not pay for a migration.
|
| No test reaches a real server. A Saloon request without a mock throws, and a
| missing fixture throws instead of being recorded from the live API. Both are
| set once here, because each call to preventStrayRequests() adds a middleware.
*/

Config::preventStrayRequests();
MockConfig::throwOnMissingFixtures();

pest()->extend(TestCase::class)->in('Feature');
