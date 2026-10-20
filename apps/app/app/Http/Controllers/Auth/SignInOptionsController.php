<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\Connections\LastSignIn;
use App\Http\Controllers\Controller;
use App\Queries\Account\SignInOptionsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every service a rider can log in with, grouped, behind "More ways to log in". From the sign up page it reads
 * as signing up and leads back there. A ticked "Remember me" comes along to each service.
 */
class SignInOptionsController extends Controller
{
    public function __invoke(Request $request, SignInOptionsQuery $signInOptionsQuery): Response|RedirectResponse
    {
        $intent = $request->query('intent') === 'register' ? 'register' : 'login';
        $options = $signInOptionsQuery->all(LastSignIn::read($request));

        if ($options['groups'] === []) {
            return to_route($intent);
        }

        return Inertia::render('auth/sign-in-options', [
            ...$options,
            'remember' => $request->boolean('remember'),
            'intent' => $intent,
        ]);
    }
}
