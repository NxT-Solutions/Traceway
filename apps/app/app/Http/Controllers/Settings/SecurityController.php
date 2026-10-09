<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\RiderId;
use App\Commands\ChangePasswordCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $twoFactorAuthenticationRequest): Response
    {
        $user = $twoFactorAuthenticationRequest->user();

        abort_unless($user instanceof User, 403);

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $user
                    ->passkeys()
                    ->getQuery()
                    ->latest()
                    ->get(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->map(fn ($passkey): array => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at?->diffForHumans() ?? '',
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $twoFactorAuthenticationRequest->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $passwordUpdateRequest, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $user = $passwordUpdateRequest->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new ChangePasswordCommand(
            riderId: new RiderId($user->id),
            password: $passwordUpdateRequest->string('password')->toString(),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
