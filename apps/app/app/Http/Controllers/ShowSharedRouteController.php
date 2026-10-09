<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Routes\SharedRouteQuery;
use App\Routes\RouteId;
use Inertia\Inertia;
use Inertia\Response;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response as OpenApiResponse;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ShowSharedRouteController extends Controller
{
    #[Get(
        path: '/share/{savedRoute}',
        operationId: 'sharedCourse',
        description: 'Requires a signed URL. The page shows the course, not the owner.',
        summary: 'Open a shared course',
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
    #[Parameter(
        name: 'signature',
        description: 'The signature on the share link.',
        in: 'query',
        required: true,
        schema: new Schema(type: 'string'),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_OK,
        description: 'The course page.',
        content: new JsonContent(ref: '#/components/schemas/SharedRoutePage'),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_FORBIDDEN,
        description: 'The link is not signed.',
    )]
    public function __invoke(string $savedRoute, SharedRouteQuery $sharedRouteQuery): Response
    {
        return Inertia::render('share', $sharedRouteQuery->page(new RouteId($savedRoute))->toArray());
    }
}
