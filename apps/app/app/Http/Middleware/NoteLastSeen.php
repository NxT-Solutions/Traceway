<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Account\RiderId;
use App\Auth\Impersonation;
use App\Contracts\Account\UserRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Notes when a rider last used Traceway, to the quarter hour, for the active riders count: in the planner, from a
 * watch, or through an AI assistant. In the planner the session remembers the last note, so the account is not
 * asked every page. A Super Admin acting as a rider does not count as that rider. A failed note never stops the
 * request.
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

        if (is_int($id) && ! Impersonation::active($request)) {
            $this->note($request, $id);
        }

        return $next($request);
    }

    private function note(Request $request, int $id): void
    {
        $now = now()->getTimestamp();
        $noted = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (is_int($noted) && $now - $noted < self::EVERY_SECONDS) {
            return;
        }

        try {
            $this->userRepository->seen(new RiderId($id), now()->toDateTimeImmutable());

            if ($request->hasSession()) {
                $request->session()->put(self::SESSION_KEY, $now);
            }
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
