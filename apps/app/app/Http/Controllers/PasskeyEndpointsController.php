<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class PasskeyEndpointsController extends Controller
{
    #[Get(
        path: '/.well-known/passkey-endpoints',
        operationId: 'passkeyEndpoints',
        summary: 'Where a passkey is enrolled',
        security: [],
        tags: ['Passkeys'],
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'Paths on this host. They do not name a person.',
        content: new JsonContent(
            required: ['enroll', 'manage'],
            properties: [
                new Property(property: 'enroll', type: 'string', format: 'uri'),
                new Property(property: 'manage', type: 'string', format: 'uri'),
            ],
            type: 'object',
        ),
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'enroll' => route('security.edit'),
            'manage' => route('security.edit'),
        ]);
    }
}
