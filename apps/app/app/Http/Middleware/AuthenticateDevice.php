<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Account\RiderId;
use App\Contracts\Account\DeviceLinkRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs in the rider behind a device token for this one request. There is no session.
 */
final readonly class AuthenticateDevice
{
    public function __construct(
        private DeviceLinkRepository $deviceLinkRepository,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $riderId = is_string($token) && $token !== ''
            ? $this->deviceLinkRepository->riderForToken(hash('sha256', $token))
            : null;

        abort_if(! $riderId instanceof RiderId || Auth::onceUsingId($riderId->value) === false, 401, 'Link this device again in Settings, then Devices.');

        return $next($request);
    }
}
