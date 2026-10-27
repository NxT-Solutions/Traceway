<?php

declare(strict_types=1);

namespace App\Integrations;

use JsonException;
use Saloon\Http\Response;
use TypeError;

/**
 * Reads a map server's answer as JSON. A proxy in front of a public server
 * can answer with a page, a bare string, or nothing at all, even with a
 * 200, and Saloon throws on each: a JsonException for a page, and a
 * TypeError for a bare value, since it keeps the decoded body as an array.
 */
final class JsonBody
{
    /** Whether the body is a JSON object or list. An empty body is not. */
    public static function readable(Response $response): bool
    {
        if (trim($response->body()) === '') {
            return false;
        }

        try {
            $response->json();
        } catch (JsonException|TypeError) {
            return false;
        }

        return true;
    }

    /** The value at a dot path, such as "trip.legs", or null when the body cannot be read. */
    public static function get(Response $response, string|int $key): mixed
    {
        return self::readable($response) ? $response->json($key) : null;
    }
}
