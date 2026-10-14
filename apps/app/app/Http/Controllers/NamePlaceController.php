<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\PlaceNamer;
use App\Http\Requests\NamePlaceRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * The planner names a dropped pin through Traceway, so the rider's browser
 * never sends the pin to the place search itself.
 */
class NamePlaceController extends Controller
{
    #[Post(
        path: '/routes/place',
        operationId: 'namePlace',
        summary: 'Name the village, town, or quarter around a point',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/NamePlaceRequest',
        description: 'A point on the map.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The name of the place, or null when the map has none or the place search did not answer.',
        content: new JsonContent(
            required: ['name'],
            properties: [
                new Property(property: 'name', type: 'string', nullable: true),
            ],
            type: 'object',
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The point is not on the map.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(NamePlaceRequest $namePlaceRequest, PlaceNamer $placeNamer): JsonResponse
    {
        try {
            $name = $placeNamer->nameAt($namePlaceRequest->float('latitude'), $namePlaceRequest->float('longitude'));
        } catch (Throwable) {
            $name = null;
        }

        return response()->json(['name' => $name]);
    }
}
