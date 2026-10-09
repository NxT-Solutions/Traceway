<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Courses\FitCourse;
use App\Http\Requests\ExportFitCourseRequest;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ExportFitCourseController extends Controller
{
    #[Post(
        path: '/routes/fit',
        operationId: 'exportFit',
        summary: 'Download a FIT course for the signed-in person',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/ExportFitCourseRequest',
        description: 'The course to write into a FIT file.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'A FIT file.',
        content: new MediaType(
            mediaType: 'application/octet-stream',
            schema: new Schema(type: 'string', format: 'binary'),
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The course is not valid.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(ExportFitCourseRequest $exportFitCourseRequest): HttpResponse
    {
        $bytes = FitCourse::encode(
            $exportFitCourseRequest->courseName(),
            $exportFitCourseRequest->sport(),
            $exportFitCourseRequest->coordinates(),
        );

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="traceway.fit"',
        ]);
    }
}
