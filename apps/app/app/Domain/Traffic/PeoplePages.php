<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * The pages and files people ask for when they use Traceway. A firewall rule that stops one of them may be
 * turning away a real visitor, not a scanner. Some of them are only ever read, so a person never sends them a
 * POST: one that a rule stops there is a scanner.
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
        '/settings', '/share/', '/routes', '/admin', '/docs/', '/build/', '/_astro/', '/oauth/', '/auth/', '/login/',
        '/api/device/', '/email/', '/reset-password/', '/.well-known/oauth-',
    ];

    /** @var list<string> */
    private const array READ_ONLY_PATHS = [
        '/', '/privacy', '/terms', '/cookies', '/support', '/status', '/llms.txt', '/robots.txt', '/favicon.ico',
    ];

    /** @var list<string> */
    private const array READ_ONLY_PREFIXES = ['/docs/', '/build/', '/_astro/'];

    /** @var list<string> */
    private const array READS = ['GET', 'HEAD', ''];

    public static function is(string $path, string $method = 'GET'): bool
    {
        if (! in_array(mb_strtoupper($method), self::READS, strict: true) && self::isReadOnly($path)) {
            return false;
        }

        if (in_array($path, self::PATHS, strict: true)) {
            return true;
        }

        return array_any(self::PREFIXES, fn (string $prefix): bool => str_starts_with($path, $prefix));
    }

    private static function isReadOnly(string $path): bool
    {
        if (in_array($path, self::READ_ONLY_PATHS, strict: true)) {
            return true;
        }

        return array_any(self::READ_ONLY_PREFIXES, fn (string $prefix): bool => str_starts_with($path, $prefix));
    }
}
