<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\Email;
use App\Account\RiderId;
use App\Account\RiderName;
use App\Commands\DeleteAccountCommand;
use App\Commands\UpdateProfileCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $profileUpdateRequest, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $user = $profileUpdateRequest->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new UpdateProfileCommand(
            riderId: new RiderId($user->id),
            name: new RiderName($profileUpdateRequest->string('name')->toString()),
            email: new Email($profileUpdateRequest->string('email')->toString()),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $profileDeleteRequest, DispatchesCommands $dispatchesCommands): SymfonyResponse
    {
        $user = $profileDeleteRequest->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new DeleteAccountCommand(new RiderId($user->id)));

        Auth::logout();

        $profileDeleteRequest->session()->invalidate();
        $profileDeleteRequest->session()->regenerateToken();

        return Inertia::location(url('/'));
    }
}
