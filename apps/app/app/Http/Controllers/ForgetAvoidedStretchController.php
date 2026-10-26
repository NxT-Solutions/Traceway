<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\RiderId;
use App\Commands\ForgetAvoidedStretchCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Models\User;
use App\Routes\AvoidedStretchId;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use OpenApi\Attributes\Delete;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ForgetAvoidedStretchController extends Controller
{
    #[Delete(
        path: '/routes/avoided/{avoidedStretch}',
        operationId: 'forgetAvoidedStretch',
        description: 'Later plans may use the stretch again. A stretch kept by someone else is rejected.',
        summary: 'Stop skipping a stretch of road',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[Parameter(
        name: 'avoidedStretch',
        description: 'The avoided stretch.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[Response(response: HttpResponse::HTTP_NO_CONTENT, description: 'The stretch is no longer skipped.')]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_FORBIDDEN,
        description: 'The stretch belongs to someone else.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'The stretch does not exist.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(Request $request, string $avoidedStretch, DispatchesCommands $dispatchesCommands): IlluminateResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new ForgetAvoidedStretchCommand(
            avoidedStretchId: new AvoidedStretchId($avoidedStretch),
            riderId: new RiderId($user->id),
        ));

        return response()->noContent();
    }
}
