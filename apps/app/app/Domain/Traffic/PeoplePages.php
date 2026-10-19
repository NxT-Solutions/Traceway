<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * The pages and files people ask for when they use Traceway. A firewall rule that stops one of them may be
 * turning away a real visitor, not a scanner.
 */
final class PeoplePages
{
    /** @var list<string> */
    private const array PATHS = [
        '/', '/login', '/register', '/forgot-password', '/two-factor-challenge', '/dashboard', '/saved', '/activity',
        '/privacy', '/terms', '/cookies', '/support', '/status', '/mcp', '/llms.txt', '/robots.txt', '/favicon.ico',
    ];

    /** @var list<string> */
    private const array PREFIXES = [
        '/settings', '/share/', '/routes', '/admin', '/docs/', '/build/', '/_astro/', '/oauth/', '/api/device/',
        '/email/', '/reset-password/', '/.well-known/oauth-',
    ];

    public static function is(string $path): bool
    {
        if (in_array($path, self::PATHS, strict: true)) {
            return true;
        }

        return array_any(self::PREFIXES, fn (string $prefix): bool => str_starts_with($path, $prefix));
    }
}
