<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\RiderId;
use App\Commands\MarkAllReleasesReadCommand;
use App\Commands\MarkReleaseReadCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Releases\ReleaseNotesQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Response as OpenApiResponse;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReleasesController extends Controller
{
    public function index(Request $request, ReleaseNotesQuery $releaseNotesQuery): Response
    {
        $user = $this->user($request);

        return Inertia::render('settings/whats-new', [
            'releases' => $releaseNotesQuery->pageFor($user->id, $user->created_at),
        ]);
    }

    #[Post(
        path: '/settings/whats-new/{release}/read',
        operationId: 'markReleaseRead',
        summary: 'Mark the notes of one release as read',
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[Parameter(
        name: 'release',
        description: 'The release.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'integer'),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_FOUND,
        description: 'Back to the page the person was on.',
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'There is no such release.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function read(Request $request, int $release, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $dispatchesCommands->dispatch(new MarkReleaseReadCommand(
            new RiderId($this->user($request)->id),
            $release,
        ));

        return back();
    }

    #[Post(
        path: '/settings/whats-new/read',
        operationId: 'markAllReleasesRead',
        summary: 'Mark the notes of every release as read',
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_FOUND,
        description: 'Back to the page the person was on.',
    )]
    public function readAll(Request $request, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $dispatchesCommands->dispatch(new MarkAllReleasesReadCommand(
            new RiderId($this->user($request)->id),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __("You're up to date.")]);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
