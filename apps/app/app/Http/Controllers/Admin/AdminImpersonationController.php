<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Account\RiderId;
use App\Commands\StartImpersonationCommand;
use App\Commands\StopImpersonationCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A Super Admin sees Traceway as a rider does, then comes back. Both ends are in the activity log.
 */
class AdminImpersonationController extends Controller
{
    public function __construct(private readonly DispatchesCommands $dispatchesCommands) {}

    public function store(Request $request, int $rider): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        try {
            $this->dispatchesCommands->dispatch(new StartImpersonationCommand(new RiderId($user->id), new RiderId($rider)));
        } catch (AuthorizationException $authorizationException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $authorizationException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'You now see Traceway as this rider. Press Stop in the bar to come back.']);

        return to_route('dashboard');
    }

    public function destroy(): RedirectResponse
    {
        $rider = $this->dispatchesCommands->dispatch(new StopImpersonationCommand);

        if (! $rider instanceof RiderId) {
            return to_route('dashboard');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'You are yourself again.']);

        return to_route('admin.users.show', ['rider' => $rider->value]);
    }
}
