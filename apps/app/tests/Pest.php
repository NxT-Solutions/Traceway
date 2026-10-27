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

/**
 * Every Composer namespace of the given vendors: 'Saloon' gives Saloon and
 * Saloon\Laravel. An arch rule finds a vendor's classes through its autoload
 * prefix, so 'Saloon' alone would miss the Laravel plugin, and a name that is
 * no prefix at all, such as 'SocialiteProviders', would match nothing.
 *
 * @return list<string>
 */
function vendorNamespaces(string ...$roots): array
{
    /** @var array<string, list<string>> $prefixes */
    $prefixes = require __DIR__.'/../vendor/composer/autoload_psr4.php';
    $namespaces = array_map(fn (string $prefix): string => rtrim($prefix, '\\'), array_keys($prefixes));
    $found = [];

    foreach ($roots as $root) {
        $under = array_filter($namespaces, fn (string $namespace): bool => $namespace === $root || str_starts_with($namespace, $root.'\\'));

        throw_if($under === [], RuntimeException::class, "No Composer namespace under {$root}, so a rule on it would check nothing.");

        array_push($found, ...$under);
    }

    return $found;
}

/**
 * The given namespaces with a closing backslash, for ignoring() and
 * toOnlyBeUsedIn(). Both compare raw prefixes, so 'App\Integrations' alone
 * would also let a class in App\IntegrationsFake through.
 *
 * @return list<string>
 */
function within(string ...$namespaces): array
{
    return array_map(fn (string $namespace): string => $namespace.'\\', $namespaces);
}
