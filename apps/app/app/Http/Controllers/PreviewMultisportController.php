<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\MultisportPlanner;
use App\Http\Requests\PreviewMultisportRequest;
use App\Http\Responses\MultisportPreview;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class PreviewMultisportController extends Controller
{
    #[Post(
        path: '/routes/multisport',
        operationId: 'previewMultisport',
        summary: 'Draw every leg of a brick, duathlon, or triathlon',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/PreviewMultisportRequest',
        description: 'The transition, an optional swim, and the land legs in order.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'Every leg, in order.',
        content: new JsonContent(ref: '#/components/schemas/MultisportPreview'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The transition or a leg is not valid.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_BAD_GATEWAY,
        description: 'A leg did not come back.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(PreviewMultisportRequest $previewMultisportRequest, MultisportPlanner $multisportPlanner): JsonResponse
    {
        try {
            $plan = $multisportPlanner->plan($previewMultisportRequest->toQuery());
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'One of the legs did not come back. Move the transition or a finish closer to a road, or try another length.',
            ], 502);
        }

        return response()->json(MultisportPreview::from($plan)->toArray());
    }
}
