<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\Email;
use App\Account\EmailChangeOutcome;
use App\Account\RiderId;
use App\Commands\CancelEmailChangeCommand;
use App\Commands\ConfirmEmailChangeCommand;
use App\Commands\ResendEmailChangeCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

/**
 * A new email the rider asked for, from the link sent to it to the swap. The link is signed without the host and
 * counts only for the rider it was sent to, signed in, and only while that email still waits.
 */
class EmailChangeController extends Controller
{
    public function confirm(Request $request, string $id, string $hash, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $user = $this->user($request);

        abort_unless(hash_equals((string) $user->id, $id), 403);

        if (! URL::hasValidSignature($request, absolute: false)) {
            return $this->back('error', 'That link has run out. Send it again from your profile.');
        }

        $pending = $user->pending_email;

        if (! is_string($pending) || ! hash_equals(sha1($pending), $hash)) {
            return $this->back('error', 'That link is for an email change that was cancelled or replaced.');
        }

        $emailChangeOutcome = $dispatchesCommands->dispatch(new ConfirmEmailChangeCommand(new RiderId($user->id), new Email($pending)));

        return match ($emailChangeOutcome) {
            EmailChangeOutcome::Changed => $this->changed($user),
            EmailChangeOutcome::Taken => $this->back('error', 'Another Traceway account uses that email now, so yours did not change.'),
            default => $this->back('error', 'That link is for an email change that was cancelled or replaced.'),
        };
    }

    public function resend(Request $request, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $dispatchesCommands->dispatch(new ResendEmailChangeCommand(new RiderId($this->user($request)->id)));

        return $this->back('success', 'The link is on its way again.');
    }

    public function destroy(Request $request, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $dispatchesCommands->dispatch(new CancelEmailChangeCommand(new RiderId($this->user($request)->id)));

        return $this->back('success', 'Your email stays as it is.');
    }

    /**
     * The same event Traceway's own confirmation link fires, so a listed Super Admin address gets its role here too.
     */
    private function changed(User $user): RedirectResponse
    {
        $fresh = $user->fresh();

        if ($fresh instanceof User) {
            event(new Verified($fresh));
        }

        return $this->back('success', 'Your account signs in with '.($fresh->email ?? 'the new email').' now.');
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('profile.edit');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
