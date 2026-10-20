<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\ConnectionFailed;
use App\Account\ConnectionProvider;
use App\Account\RiderId;
use App\Auth\Connections\ConnectionFlow;
use App\Auth\Connections\ConnectionIntent;
use App\Commands\DisconnectConnectionCommand;
use App\Contracts\Auth\IdentityProviders;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Account\ConnectionsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;
use OpenApi\Attributes\Delete;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response as OpenApiResponse;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The accounts at other services a rider connects: to log in with, to send routes to, and later to share and
 * get notified through. Each connects through the service's own sign-in page.
 */
class ConnectionsController extends Controller
{
    public function edit(Request $request, ConnectionsQuery $connectionsQuery): Response
    {
        $user = $this->user($request);

        return Inertia::render('settings/connections', [
            ...$connectionsQuery->page($user->id)->toArray(),
            // Connect and Disconnect wait until the email is confirmed, so the page says so instead of offering them.
            'emailVerified' => $user->hasVerifiedEmail(),
            'verificationSent' => $request->session()->get('status') === Fortify::VERIFICATION_LINK_SENT,
        ]);
    }

    /**
     * Sends the rider to the service to allow Traceway. Adding a way to log in is a sensitive step, so the route
     * asks for the password, a passkey, or another connection first. The return path brings the rider back to
     * where they started, such as Send to device.
     */
    public function connect(Request $request, string $provider, IdentityProviders $identityProviders): RedirectResponse
    {
        $connectionProvider = ConnectionProvider::tryFrom($provider);

        abort_unless($connectionProvider instanceof ConnectionProvider && $identityProviders->isEnabled($connectionProvider), 404);

        new ConnectionFlow($request->session())->start(ConnectionIntent::Connect, $connectionProvider, $this->user($request)->id, return: ConnectionFlow::safeReturn($request->query('return')));

        try {
            return redirect()->away($identityProviders->redirectUrl($connectionProvider));
        } catch (ConnectionFailed $connectionFailed) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $connectionFailed->getMessage()]);

            return to_route('connections.edit');
        }
    }

    #[Delete(
        path: '/settings/connections/{provider}',
        operationId: 'disconnectConnection',
        description: 'Traceway asks the service to withdraw the keys it gave, when it holds any, then forgets the connection and the routes sent through it. A rider cannot disconnect their last way to log in.',
        summary: 'Disconnect an account at another service',
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[Parameter(
        name: 'provider',
        description: 'The service.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', enum: ['google', 'apple', 'facebook', 'instagram', 'x', 'github', 'discord', 'strava', 'garmin_connect', 'wahoo', 'ride_with_gps', 'runkeeper', 'runsignup', 'openstreetmap']),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_FOUND,
        description: 'Back to Connections, with what happened.',
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'Traceway does not know that service.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function destroy(Request $request, string $provider, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $connectionProvider = ConnectionProvider::tryFrom($provider);

        abort_unless($connectionProvider instanceof ConnectionProvider, 404);

        try {
            $dispatchesCommands->dispatch(new DisconnectConnectionCommand(new RiderId($this->user($request)->id), $connectionProvider));
        } catch (ConnectionFailed $connectionFailed) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $connectionFailed->getMessage()]);

            return back(fallback: route('connections.edit'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $connectionProvider->label().' disconnected.']);

        return back(fallback: route('connections.edit'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
