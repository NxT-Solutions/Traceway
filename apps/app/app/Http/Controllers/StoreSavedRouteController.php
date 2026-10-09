<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Bus\DispatchesCommands;
use App\Http\Requests\StoreSavedRouteRequest;
use App\Http\ValidatedRoute;
use App\Models\User;
use App\Routes\SavedRouteLink;
use Illuminate\Http\JsonResponse;

class StoreSavedRouteController extends Controller
{
    public function __invoke(StoreSavedRouteRequest $storeSavedRouteRequest, DispatchesCommands $dispatchesCommands): JsonResponse
    {
        $user = $storeSavedRouteRequest->user();

        abort_unless($user instanceof User, 403);

        /** @var array<string, mixed> $data */
        $data = $storeSavedRouteRequest->validated();
        $link = $dispatchesCommands->dispatch(ValidatedRoute::command($user->id, $data));

        abort_unless($link instanceof SavedRouteLink, 500);

        return response()->json([
            'id' => $link->id,
            'url' => $link->url,
        ]);
    }
}
