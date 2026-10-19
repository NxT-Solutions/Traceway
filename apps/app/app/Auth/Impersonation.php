<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Http\Request;

/**
 * Whether the browser's session is a Super Admin acting as a rider, and who that Super Admin is.
 */
final class Impersonation
{
    public const string SESSION_KEY = 'impersonator_id';

    public static function active(Request $request): bool
    {
        return self::actorId($request) !== null;
    }

    public static function actorId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);

        return is_int($id) ? $id : null;
    }
}
