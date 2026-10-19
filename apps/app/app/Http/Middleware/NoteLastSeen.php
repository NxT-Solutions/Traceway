<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Account\RiderId;
use App\Contracts\Account\UserRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notes when a signed-in rider last used Traceway, for the active riders count. The session remembers the last
 * note, so the account is written at most once every quarter of an hour.
 */
final readonly class NoteLastSeen
{
    public const int EVERY_SECONDS = 900;

    private const string SESSION_KEY = 'last_seen_noted_at';

    public function __construct(private UserRepository $userRepository) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->user()?->getAuthIdentifier();

        if (is_int($id) && $request->hasSession()) {
            $noted = $request->session()->get(self::SESSION_KEY);
            $now = now()->getTimestamp();

            if (! is_int($noted) || $now - $noted >= self::EVERY_SECONDS) {
                $this->userRepository->seen(new RiderId($id), now()->toDateTimeImmutable());
                $request->session()->put(self::SESSION_KEY, $now);
            }
        }

        return $next($request);
    }
}
