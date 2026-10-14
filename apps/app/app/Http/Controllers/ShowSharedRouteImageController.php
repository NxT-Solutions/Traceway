<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Routes\SharedRouteImageQuery;
use App\Routes\RouteId;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response as OpenApiResponse;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ShowSharedRouteImageController extends Controller
{
    #[Get(
        path: '/share/{savedRoute}/image',
        operationId: 'sharedCourseImage',
        description: 'Requires a signed URL. Until the rider has opened the route in a browser, this is the Traceway picture.',
        summary: 'The picture a chat shows for a shared course',
        security: [],
        tags: ['Share'],
    )]
    #[Parameter(
        name: 'savedRoute',
        description: 'The saved route.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_OK,
        description: 'The route on the map, 1200 by 630.',
        content: [
            new MediaType(mediaType: 'image/jpeg', schema: new Schema(type: 'string', format: 'binary')),
            new MediaType(mediaType: 'image/png', schema: new Schema(type: 'string', format: 'binary')),
        ],
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_FORBIDDEN,
        description: 'The link is not signed.',
    )]
    public function __invoke(string $savedRoute, SharedRouteImageQuery $sharedRouteImageQuery): HttpResponse
    {
        $jpeg = $sharedRouteImageQuery->find(new RouteId($savedRoute));

        if ($jpeg === null) {
            return response()->file(public_path('images/share-route.png'), [
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }
}
