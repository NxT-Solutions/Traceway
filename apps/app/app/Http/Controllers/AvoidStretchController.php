<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\RiderId;
use App\Commands\AvoidStretchCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\MapServerBusy;
use App\Http\Requests\AvoidStretchRequest;
use App\Http\Responses\AvoidedStretchResponse;
use App\Models\User;
use App\Routes\TooManyAvoidedStretches;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class AvoidStretchController extends Controller
{
    #[Post(
        path: '/routes/avoided',
        operationId: 'avoidStretch',
        description: 'Finds the way under the point and keeps it, so every later plan skips it. The same way twice keeps the first.',
        summary: 'Skip a stretch of road from now on',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/AvoidStretchRequest',
        description: 'A tapped point, and the sport and surface used to find the way under it.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_CREATED,
        description: 'The stretch now skipped.',
        content: new JsonContent(ref: '#/components/schemas/AvoidedStretch'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'No road or path is close enough, or the rider already skips as many stretches as allowed.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_SERVICE_UNAVAILABLE,
        description: 'The routing engine is busy or did not answer. Try again in a moment.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(AvoidStretchRequest $avoidStretchRequest, DispatchesCommands $dispatchesCommands): JsonResponse
    {
        $user = $avoidStretchRequest->user();

        abort_unless($user instanceof User, 403);

        try {
            $avoided = $dispatchesCommands->dispatch(new AvoidStretchCommand(
                riderId: new RiderId($user->id),
                latitude: $avoidStretchRequest->float('latitude'),
                longitude: $avoidStretchRequest->float('longitude'),
                sport: $avoidStretchRequest->sport(),
                surface: $avoidStretchRequest->surface(),
            ));
        } catch (TooManyAvoidedStretches) {
            return response()->json([
                'message' => 'You already avoid '.TooManyAvoidedStretches::MOST.' roads. Remove one you no longer need first.',
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (MapServerBusy) {
            return response()->json([
                'message' => 'The map is busy. Try again in a moment.',
            ], HttpResponse::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $throwable) {
            // Only the kind of failure: the request holds where the rider is.
            report(new RuntimeException('Avoiding a stretch failed with '.$throwable::class.'.'));

            return response()->json([
                'message' => 'The map is busy. Try again in a moment.',
            ], HttpResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! $avoided instanceof AvoidedStretch) {
            return response()->json([
                'message' => 'No road or path is close enough there. Tap right on the road.',
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(new AvoidedStretchResponse($avoided)->toArray(), HttpResponse::HTTP_CREATED);
    }
}
