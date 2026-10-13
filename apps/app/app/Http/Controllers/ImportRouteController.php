<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Courses\GpxImport;
use App\Http\Requests\ImportRouteRequest;
use App\Http\Responses\ImportedRoute;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ImportRouteController extends Controller
{
    #[Post(
        path: '/routes/import',
        operationId: 'importRoute',
        description: 'Reads the line out of a GPX file. Nothing is saved.',
        summary: 'Open a GPX file in the planner',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/ImportRouteRequest',
        description: 'A GPX file to plan from.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The line in the file.',
        content: new JsonContent(ref: '#/components/schemas/ImportedRoute'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The file is not a GPX file with a route in it.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(ImportRouteRequest $importRouteRequest): JsonResponse
    {
        try {
            $importedCourse = GpxImport::parse($importRouteRequest->contents());
        } catch (InvalidArgumentException $invalidArgumentException) {
            return response()->json(['message' => $invalidArgumentException->getMessage()], 422);
        }

        return response()->json(new ImportedRoute($importedCourse->name, $importedCourse->coordinates)->toArray());
    }
}
