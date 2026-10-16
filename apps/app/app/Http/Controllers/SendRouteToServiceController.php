<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\RiderId;
use App\Account\RouteService;
use App\Commands\SendRouteToServiceCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Requests\SendRouteToServiceRequest;
use App\Models\User;
use App\Routes\RouteId;
use App\Routes\SentRoute;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SendRouteToServiceController extends Controller
{
    #[Post(
        path: '/routes/saved/{savedRoute}/send/{service}',
        operationId: 'sendRouteToService',
        description: 'Wahoo syncs the route to the ELEMNT. Ride with GPS keeps it in the library, and the rider sends it on from there with Send to Device, for a Karoo or Bryton. A second send replaces the first copy.',
        summary: 'Send a saved route to a connected service',
        security: [['session' => []]],
        tags: ['Library'],
    )]
    #[Parameter(
        name: 'savedRoute',
        description: 'The saved route.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[Parameter(
        name: 'service',
        description: 'The service.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', enum: ['wahoo', 'ride_with_gps']),
    )]
    #[RequestBody(ref: '#/components/requestBodies/SendRouteToServiceRequest', required: false)]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The route is at the service, or the service is still reading it.',
        content: new JsonContent(
            required: ['status', 'service', 'message'],
            properties: [
                new Property(property: 'status', type: 'string', enum: ['sent', 'processing']),
                new Property(property: 'service', type: 'string'),
                new Property(property: 'message', type: 'string'),
                new Property(property: 'url', description: 'The route at the service, when it has a page.', type: 'string', nullable: true),
            ],
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'The rider has no such route or land leg, or this Traceway does not send to that service.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_CONFLICT,
        description: 'The service is not connected, or it needs connecting again.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_BAD_GATEWAY,
        description: 'The service did not take the route just now.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(SendRouteToServiceRequest $sendRouteToServiceRequest, string $savedRoute, string $service, DispatchesCommands $dispatchesCommands): JsonResponse
    {
        $user = $sendRouteToServiceRequest->user();
        $routeService = RouteService::tryFrom($service);

        abort_unless($user instanceof User, 401);
        abort_unless($routeService instanceof RouteService, 404);

        $sentRoute = $dispatchesCommands->dispatch(new SendRouteToServiceCommand(
            new RiderId($user->id),
            new RouteId($savedRoute),
            $sendRouteToServiceRequest->leg(),
            $routeService,
        ));

        abort_unless($sentRoute instanceof SentRoute, 500);

        return response()->json([
            'status' => $sentRoute->processing ? 'processing' : 'sent',
            'service' => $routeService->value,
            'message' => $this->message($routeService, $sentRoute),
            'url' => $sentRoute->url,
        ]);
    }

    private function message(RouteService $routeService, SentRoute $sentRoute): string
    {
        if ($routeService === RouteService::Wahoo) {
            return 'In your Wahoo app. It syncs to your ELEMNT or ACE the next time it connects.';
        }

        return $sentRoute->processing
            ? 'Ride with GPS is reading it. It shows in your library in a minute. Choose Send to Device there to put it on your bike computer.'
            : 'In your Ride with GPS library. Choose Send to Device there to put it on your bike computer.';
    }
}
