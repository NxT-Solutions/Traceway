<?php

declare(strict_types=1);

namespace App\Http\Controllers\Device;

use App\Commands\UnlinkDeviceCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes\Delete;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response as OpenApiResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DeviceLinkController extends Controller
{
    #[Delete(
        path: '/api/device/link',
        operationId: 'unlinkDevice',
        description: 'The rider chose Unlink device on the device. It leaves Settings, then Devices, and its token stops working at once.',
        summary: 'Unlink the device that sends the token',
        security: [['deviceToken' => []]],
        tags: ['Device'],
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_NO_CONTENT,
        description: 'The device is unlinked.',
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'The token is unknown, or the device was already removed.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function destroy(Request $request, DispatchesCommands $dispatchesCommands): Response
    {
        $dispatchesCommands->dispatch(new UnlinkDeviceCommand(hash('sha256', (string) $request->bearerToken())));

        return response()->noContent();
    }
}
