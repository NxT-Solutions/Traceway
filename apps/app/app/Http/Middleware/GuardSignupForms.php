<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\HumanCheck;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creating an account, asking for a reset link, and finishing a sign-up that started at another service each
 * send an email. This guards them: a few tries a minute from one address, and the Turnstile check when it is set
 * up. The finish form also sends only a few links an hour to one email, and for one account at a service.
 */
class GuardSignupForms
{
    private const array FORMS = ['register.store', 'password.email', 'connections.finish.store'];

    /**
     * Fortify owns these routes, so the limit per address is counted here. The finish form's route counts its own.
     */
    private const array FORTIFY_FORMS = ['register.store', 'password.email'];

    public function __construct(
        private readonly ThrottleRequests $throttleRequests,
        private readonly HumanCheck $humanCheck,
    ) {}

    /**
     * The public Turnstile key for the page that shows one of these forms, or null when the check is off. Both
     * keys are needed, the same as for the check itself.
     */
    public static function siteKey(): ?string
    {
        $siteKey = config('services.turnstile.site_key');
        $secret = config('services.turnstile.secret_key');

        return is_string($siteKey) && $siteKey !== '' && is_string($secret) && $secret !== '' ? $siteKey : null;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs(...self::FORMS)) {
            /** @var Response */
            return $next($request);
        }

        if (! $request->routeIs(...self::FORTIFY_FORMS)) {
            // The person check first, so a script cannot use up the links for someone else's email.
            $this->checkPerson($request);

            /** @var Response */
            return $this->throttleRequests->handle($request, $next, 'sign-up-links');
        }

        return $this->throttleRequests->handle($request, function (Request $request) use ($next): Response {
            $this->checkPerson($request);

            /** @var Response */
            return $next($request);
        }, 'signup');
    }

    private function checkPerson(Request $request): void
    {
        $token = $request->input('cf-turnstile-response');

        try {
            $passes = $this->humanCheck->passes(is_string($token) ? $token : '', (string) $request->ip());
        } catch (RuntimeException $runtimeException) {
            // Cloudflare did not answer. The rate limit still holds, so let a
            // person through rather than lock everyone out.
            report($runtimeException);

            return;
        }

        throw_unless($passes, ValidationException::withMessages([
            'turnstile' => 'Traceway could not check that you are a person. Wait for the check above the button to finish, then try again.',
        ]));
    }
}
