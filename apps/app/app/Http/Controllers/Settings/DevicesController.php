<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\ConnectionProvider;
use App\Account\DeviceLabel;
use App\Account\DeviceLinkId;
use App\Account\PairingCode;
use App\Account\RiderId;
use App\Account\RouteService;
use App\Commands\LinkDeviceCommand;
use App\Commands\RevokeDeviceCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LinkDeviceRequest;
use App\Models\User;
use App\Queries\Account\ConnectionsQuery;
use App\Queries\Account\DeviceLinksQuery;
use App\Queries\Account\RouteServicesQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;
use OpenApi\Attributes\Delete;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response as OpenApiResponse;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DevicesController extends Controller
{
    public function edit(Request $request, DeviceLinksQuery $deviceLinksQuery, RouteServicesQuery $routeServicesQuery, ConnectionsQuery $connectionsQuery): Response
    {
        $user = $this->user($request);
        $riderId = $user->id;

        return Inertia::render('settings/devices', [
            'devices' => $deviceLinksQuery->forRider($riderId),
            'services' => $routeServicesQuery->forRider($riderId),
            // Why Disconnect is turned off for a service, the same as on the connections page, or null.
            'disconnectBlocked' => $connectionsQuery->disconnectBlocked(
                $riderId,
                array_map(static fn (RouteService $routeService): ConnectionProvider => $routeService->connection(), RouteService::cases()),
            ),
            // Connecting or disconnecting a service waits until the email is confirmed, the same as on the
            // connections page.
            'emailVerified' => $user->hasVerifiedEmail(),
            'verificationSent' => $request->session()->get('status') === Fortify::VERIFICATION_LINK_SENT,
            'garminApp' => $this->garminApp(),
        ]);
    }

    /**
     * The Traceway app's page in the Connect IQ store, where a rider adds it to a Garmin. Null when no store id is set.
     */
    private function garminApp(): ?string
    {
        $url = config('services.connect_iq.url');
        $storeId = config('services.connect_iq.store_id');

        if (! is_string($storeId) || $storeId === '') {
            return null;
        }

        return rtrim(is_string($url) && $url !== '' ? $url : 'https://apps.garmin.com', '/').'/apps/'.rawurlencode($storeId);
    }

    #[Post(
        path: '/settings/devices',
        operationId: 'linkDevice',
        summary: 'Link a device to the signed-in person with the code it shows',
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[RequestBody(ref: '#/components/requestBodies/LinkDeviceRequest', required: true)]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_FOUND,
        description: 'Back to Devices.',
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The code is not waiting, or the label is not valid.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function store(LinkDeviceRequest $linkDeviceRequest, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $user = $this->user($linkDeviceRequest);

        $dispatchesCommands->dispatch(new LinkDeviceCommand(
            new RiderId($user->id),
            new PairingCode($linkDeviceRequest->string('code')->toString()),
            new DeviceLabel($linkDeviceRequest->string('label')->toString()),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device linked. It will show your routes in a moment.')]);

        return to_route('devices.edit');
    }

    #[Delete(
        path: '/settings/devices/{deviceLink}',
        operationId: 'revokeDevice',
        summary: 'Remove a linked device. Its token stops working at once.',
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[Parameter(
        name: 'deviceLink',
        description: 'The linked device.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_FOUND,
        description: 'Back to Devices.',
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'The person has no such device.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function destroy(Request $request, string $deviceLink, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $dispatchesCommands->dispatch(new RevokeDeviceCommand(
            new RiderId($this->user($request)->id),
            new DeviceLinkId($deviceLink),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device removed.')]);

        return to_route('devices.edit');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
