<?php

declare(strict_types=1);

namespace App\Providers;

use App\Account\Email;
use App\Account\Role;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\DeletePasskeyKeepingAWayIn;
use App\Actions\Fortify\ResetUserPassword;
use App\Auth\Connections\ConnectionFlow;
use App\Auth\Connections\LastSignIn;
use App\Http\Middleware\GuardSignupForms;
use App\Http\Responses\LogoutResponse;
use App\Models\User;
use App\Queries\Account\ConnectionsQuery;
use App\Queries\Account\SignInOptionsQuery;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->bind(DeletePasskey::class, DeletePasskeyKeepingAWayIn::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureChallenges();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * A password that leads to a two-factor challenge takes the place of a service the rider started with and
     * left, so the code they give next is not marked "Last used" as that service.
     */
    private function configureChallenges(): void
    {
        Event::listen(TwoFactorAuthenticationChallenged::class, static function (): void {
            $request = request();

            if ($request->routeIs('login.store') && $request->hasSession()) {
                new ConnectionFlow($request->session())->pullSigningInWith();
            }
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'demoRiders' => config('demo.enabled') === true ? $this->demoRiders() : [],
            'connections' => resolve(SignInOptionsQuery::class)->forLogin(LastSignIn::read($request)),
            'pendingConnection' => $this->pendingConnection($request),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
            'turnstileSiteKey' => GuardSignupForms::siteKey(),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn (Request $request) => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'turnstileSiteKey' => GuardSignupForms::siteKey(),
            'connections' => resolve(SignInOptionsQuery::class)->forLogin(LastSignIn::read($request)),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        // A rider without a password confirms with a passkey or a connected service. A rider with one gives it.
        Fortify::confirmPasswordView(function (Request $request) {
            $user = $request->user();
            $hasPassword = ! $user instanceof User || $user->password !== null;

            return Inertia::render('auth/confirm-password', [
                'hasPassword' => $hasPassword,
                'connections' => $user instanceof User && ! $hasPassword ? resolve(ConnectionsQuery::class)->signInMethods($user->id) : [],
            ]);
        });
    }

    /**
     * A service waiting to be connected once the rider logs in, to the account with its email or, with confirm,
     * to the one they choose and then say yes. An address is shown shortened, in case someone else uses the
     * computer next.
     *
     * @return array{slug: string, label: string, email: string|null, confirm: bool}|null
     */
    private function pendingConnection(Request $request): ?array
    {
        $pending = new ConnectionFlow($request->session())->pendingLink();

        if ($pending === null) {
            return null;
        }

        return [
            'slug' => $pending['identity']->provider->value,
            'label' => $pending['identity']->provider->label(),
            'email' => $pending['email'] instanceof Email ? ConnectionFlow::shortEmail($pending['email']) : null,
            'confirm' => $pending['confirm'],
        ];
    }

    /**
     * @return array<int, array{key: string, name: string, description: string, role: string}>
     */
    private function demoRiders(): array
    {
        /** @var array<string, array{name: string, email: string, description: string, role?: string}> $riders */
        $riders = config('demo.riders', []);

        return collect($riders)
            ->map(fn (array $rider, string $key): array => [
                'key' => $key,
                'name' => $rider['name'],
                'description' => $rider['description'],
                'role' => (Role::tryFrom($rider['role'] ?? '') ?? Role::User)->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        RateLimiter::for('login', function (Request $request) {
            $username = $request->input(Fortify::username());
            $throttleKey = Str::transliterate(Str::lower(is_string($username) ? $username : '').'|'.($request->ip() ?? ''));

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', fn (Request $request) => Limit::perMinute(10)->by(
            (is_string($credentialId = $request->input('credential.id')) ? $credentialId : $request->session()->getId()).'|'.($request->ip() ?? ''),
        ));
    }
}
