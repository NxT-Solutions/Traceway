<?php

declare(strict_types=1);

namespace App\Providers;

use App\Account\Role;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LogoutResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
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
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'demoRiders' => config('demo.enabled') === true ? $this->demoRiders() : [],
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
            'turnstileSiteKey' => $this->turnstileSiteKey(),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'turnstileSiteKey' => $this->turnstileSiteKey(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * The public Turnstile key, or null when the check is off. Both keys are
     * needed, the same as for the check itself.
     */
    private function turnstileSiteKey(): ?string
    {
        $siteKey = config('services.turnstile.site_key');
        $secret = config('services.turnstile.secret_key');

        return is_string($siteKey) && $siteKey !== '' && is_string($secret) && $secret !== '' ? $siteKey : null;
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
