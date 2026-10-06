<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    public function __invoke(string $rider): RedirectResponse
    {
        abort_unless(config('demo.enabled') === true, 404);

        $email = config("demo.riders.{$rider}.email");
        abort_unless(is_string($email), 404);

        $user = User::query()->where('email', $email)->first();
        abort_unless($user instanceof User, 404);

        Auth::login($user);

        return redirect()->intended(route('dashboard'));
    }
}
