<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\PlaceMatch;
use App\Domain\Routing\PlaceSearch;
use App\Http\Requests\SearchPlacesRequest;
use App\Routes\PlaceSearchBusy;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * The planner finds a town, a street, or a landmark through Traceway, so the
 * rider's browser never sends what they typed to the place search itself.
 */
class SearchPlacesController extends Controller
{
    #[Post(
        path: '/routes/places',
        operationId: 'searchPlaces',
        summary: 'Find places by name, the ones near the map first',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/SearchPlacesRequest',
        description: 'What a rider typed, and the middle of the map they look at.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'Up to five places. None when nothing matches.',
        content: new JsonContent(
            required: ['places'],
            properties: [
                new Property(property: 'places', type: 'array', items: new Items(
                    required: ['name', 'area', 'latitude', 'longitude', 'kind'],
                    properties: [
                        new Property(property: 'name', type: 'string'),
                        new Property(property: 'area', type: 'string'),
                        new Property(property: 'latitude', type: 'number'),
                        new Property(property: 'longitude', type: 'number'),
                        new Property(property: 'kind', type: 'string', enum: ['address', 'street', 'town', 'place']),
                    ],
                    type: 'object',
                )),
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
        description: 'The search is too short or too long, or the point is not on the map.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_TOO_MANY_REQUESTS,
        description: 'Too many searches in a minute.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_SERVICE_UNAVAILABLE,
        description: 'The place search is busy or did not answer. Try again in a moment.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(SearchPlacesRequest $searchPlacesRequest, PlaceSearch $placeSearch): JsonResponse
    {
        try {
            $matches = $placeSearch->search(
                $searchPlacesRequest->string('query')->toString(),
                $searchPlacesRequest->float('latitude'),
                $searchPlacesRequest->float('longitude'),
            );
        } catch (PlaceSearchBusy) {
            return $this->busy();
        } catch (Throwable $throwable) {
            // Only the kind of failure: the message of a timeout holds the
            // URL, and with it what the rider typed.
            report(new RuntimeException('The place search failed with '.$throwable::class.'.'));

            return $this->busy();
        }

        return response()->json([
            'places' => array_map(static fn (PlaceMatch $placeMatch): array => [
                'name' => $placeMatch->name,
                'area' => $placeMatch->area,
                'latitude' => $placeMatch->latitude,
                'longitude' => $placeMatch->longitude,
                'kind' => $placeMatch->kind->value,
            ], $matches),
        ]);
    }

    private function busy(): JsonResponse
    {
        return response()->json([
            'message' => 'Search is busy. Try again in a moment.',
        ], HttpResponse::HTTP_SERVICE_UNAVAILABLE);
    }
}
