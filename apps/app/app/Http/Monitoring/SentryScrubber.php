<?php

declare(strict_types=1);

namespace App\Http\Monitoring;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Logs\Log;

/**
 * Takes what can point at a rider out of what goes to Sentry, on top of the
 * `data_collection` options in config/sentry.php.
 *
 * A path can hold a password reset token or a signed email link, so a page
 * address goes out as its route, such as `/reset-password/{token}`. Laravel
 * adds the signed-in rider's id to every logged exception, so that goes too.
 * The config names these methods as strings, which `config:cache` can keep.
 */
final class SentryScrubber
{
    /**
     * The log context and breadcrumb keys that name a rider.
     */
    private const array RIDER_KEYS = ['userId', 'user_id'];

    public static function event(Event $event): Event
    {
        $request = $event->getRequest();

        if (isset($request['url'])) {
            $request['url'] = self::routeUrl();
            $event->setRequest($request);
        }

        $trace = $event->getContexts()['trace'] ?? null;

        if (is_array($trace) && is_array($trace['data'] ?? null) && isset($trace['data']['url'])) {
            $trace['data']['url'] = self::routePath();
            $event->setContext('trace', $trace);
        }

        return $event;
    }

    public static function log(Log $log): Log
    {
        foreach (self::RIDER_KEYS as $key) {
            $log->attributes()->forget($key);
        }

        return $log;
    }

    public static function breadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        foreach (self::RIDER_KEYS as $key) {
            $breadcrumb = $breadcrumb->withoutMetadata($key);
        }

        return $breadcrumb;
    }

    /**
     * The address of the current page with its route in place of its path.
     */
    private static function routeUrl(): string
    {
        $request = self::request();

        if (! $request instanceof Request) {
            return self::routePath();
        }

        return $request->getSchemeAndHttpHost().self::routePath();
    }

    /**
     * The route of the current page, or `/` when no route matched.
     */
    private static function routePath(): string
    {
        $route = self::request()?->route();

        if (! $route instanceof Route) {
            return '/';
        }

        return '/'.ltrim($route->uri(), '/');
    }

    private static function request(): ?Request
    {
        return app()->bound('request') ? resolve('request') : null;
    }
}
