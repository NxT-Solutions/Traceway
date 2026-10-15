<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\Sport;
use App\Http\Requests\SnapPointRequest;
use App\Integrations\Valhalla\Requests\LocateRequest;
use App\Integrations\Valhalla\ValhallaConnector;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class SnapPointController extends Controller
{
    #[Post(
        path: '/routes/snap',
        operationId: 'snapPoint',
        summary: 'Move a point onto the nearest road or path',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/SnapPointRequest',
        description: 'A point and the sport used to choose a road or a path.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The snapped point.',
        content: new JsonContent(
            required: ['latitude', 'longitude'],
            properties: [
                new Property(property: 'latitude', type: 'number'),
                new Property(property: 'longitude', type: 'number'),
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
        description: 'Nothing is close enough.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_SERVICE_UNAVAILABLE,
        description: 'The routing engine is busy or did not answer. Try again in a moment.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(SnapPointRequest $snapPointRequest, ValhallaConnector $valhallaConnector): JsonResponse
    {
        $sport = $snapPointRequest->sport();
        $latitude = $snapPointRequest->float('latitude');
        $longitude = $snapPointRequest->float('longitude');
        $onFoot = match ($sport) {
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => true,
            Sport::Road, Sport::Gravel, Sport::Mtb => false,
        };

        // A router that did not answer is not a spot without roads: the
        // rider tries again rather than looking for another spot.
        try {
            $response = $valhallaConnector->send(new LocateRequest(
                latitude: $latitude,
                longitude: $longitude,
                costing: $onFoot ? 'pedestrian' : 'bicycle',
            ));

            if ($response->failed()) {
                return $this->busy();
            }

            $latitude = $response->json('0.edges.0.correlated_lat');
            $longitude = $response->json('0.edges.0.correlated_lon');
        } catch (Throwable $throwable) {
            // A timeout or a lost connection. Only the kind of failure: the
            // request holds where the rider is.
            report(new RuntimeException('The road snap failed with '.$throwable::class.'.'));

            return $this->busy();
        }

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return $this->miss();
        }

        return response()->json([
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ]);
    }

    private function miss(): JsonResponse
    {
        return response()->json([
            'message' => 'No road or path is close enough to put a point there.',
        ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function busy(): JsonResponse
    {
        return response()->json([
            'message' => 'The map is busy. Try again in a moment.',
        ], HttpResponse::HTTP_SERVICE_UNAVAILABLE);
    }
}
