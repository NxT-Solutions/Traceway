<?php

declare(strict_types=1);

namespace App\Http\Controllers\Device;

use App\Account\DevicePairing;
use App\Commands\ClaimDevicePairingCommand;
use App\Commands\StartDevicePairingCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimDevicePairingRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DevicePairingController extends Controller
{
    #[Post(
        path: '/api/device/pairings',
        operationId: 'startDevicePairing',
        description: 'The device shows the code. The rider types it in Settings, then Devices. The device keeps the secret and asks for its token with it.',
        summary: 'Open a pairing for a device',
        security: [],
        tags: ['Device'],
    )]
    #[Response(
        response: HttpResponse::HTTP_CREATED,
        description: 'A code for the rider and a secret for the device.',
        content: new JsonContent(
            required: ['code', 'secret', 'expires_in'],
            properties: [
                new Property(property: 'code', type: 'string', maxLength: 6, minLength: 6),
                new Property(property: 'secret', type: 'string'),
                new Property(property: 'expires_in', description: 'Seconds until the code runs out.', type: 'integer'),
            ],
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_TOO_MANY_REQUESTS,
        description: 'Too many pairings from this address.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function start(DispatchesCommands $dispatchesCommands): JsonResponse
    {
        $devicePairing = $dispatchesCommands->dispatch(new StartDevicePairingCommand);

        abort_unless($devicePairing instanceof DevicePairing, 500);

        return response()->json($devicePairing->toArray(), 201);
    }

    #[Post(
        path: '/api/device/pairings/claim',
        operationId: 'claimDevicePairing',
        description: 'The device asks every few seconds. The token comes back once.',
        summary: 'Collect the device token after the rider enters the code',
        security: [],
        tags: ['Device'],
    )]
    #[RequestBody(ref: '#/components/requestBodies/ClaimDevicePairingRequest', required: true)]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The rider linked the device. Send the token as a Bearer token.',
        content: new JsonContent(
            required: ['token'],
            properties: [new Property(property: 'token', type: 'string')],
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_ACCEPTED,
        description: 'No rider has entered the code yet.',
        content: new JsonContent(
            required: ['status'],
            properties: [new Property(property: 'status', type: 'string', enum: ['waiting'])],
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_GONE,
        description: 'The code ran out, or the token was already collected.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function claim(ClaimDevicePairingRequest $claimDevicePairingRequest, DispatchesCommands $dispatchesCommands): JsonResponse
    {
        $token = $dispatchesCommands->dispatch(new ClaimDevicePairingCommand($claimDevicePairingRequest->secret()));

        if (! is_string($token)) {
            return response()->json(['status' => 'waiting'], 202);
        }

        return response()->json(['token' => $token]);
    }
}
