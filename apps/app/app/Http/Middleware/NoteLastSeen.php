<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Account\RiderId;
use App\Contracts\Account\UserRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Notes when a signed-in rider last used Traceway, to the quarter hour, for the active riders count. The session
 * remembers the last note, so the account is written at most once every quarter of an hour. A failed note never
 * stops the page.
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
                try {
                    // Kept to the quarter hour, as the privacy policy says.
                    $this->userRepository->seen(new RiderId($id), now()->setTimestamp($now - $now % self::EVERY_SECONDS)->toDateTimeImmutable());
                    $request->session()->put(self::SESSION_KEY, $now);
                } catch (Throwable $throwable) {
                    report($throwable);
                }
            }
        }

        return $next($request);
    }
}
