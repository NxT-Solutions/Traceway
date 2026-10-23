<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\RiderId;
use App\Commands\FinishTourCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FinishTourController extends Controller
{
    #[Post(
        path: '/dashboard/tour',
        operationId: 'finishTour',
        description: 'Finishing the tour and closing it early both count. Sent again, it changes nothing.',
        summary: 'Note that the planner showed the signed-in person around',
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[Response(response: HttpResponse::HTTP_NO_CONTENT, description: 'The planner will not show the tour on its own again.')]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(Request $request, DispatchesCommands $dispatchesCommands): HttpResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new FinishTourCommand(new RiderId($user->id)));

        return response()->noContent();
    }
}
