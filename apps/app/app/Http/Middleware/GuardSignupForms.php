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
 * Creating an account and asking for a reset link each send an email. Fortify
 * owns both forms, so this guards them: a few tries a minute from one
 * address, and the Turnstile check when it is set up.
 */
class GuardSignupForms
{
    private const array FORMS = ['register.store', 'password.email'];

    public function __construct(
        private readonly ThrottleRequests $throttleRequests,
        private readonly HumanCheck $humanCheck,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs(...self::FORMS)) {
            /** @var Response */
            return $next($request);
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
