<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\RiderId;
use App\Commands\ExportPersonalDataCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Account\PersonalDataQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response as OpenApiResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyController extends Controller
{
    public function edit(Request $request, PersonalDataQuery $personalDataQuery): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('settings/privacy', $personalDataQuery->page($user->id)->toArray());
    }

    #[Get(
        path: '/settings/privacy/export',
        operationId: 'exportPersonalData',
        description: 'The file is that person only. The password, two-factor secret, recovery codes, and remember token are withheld.',
        summary: "Download the signed-in person's own data",
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_OK,
        description: 'A JSON file of the signed-in account and their routes.',
        content: new JsonContent(ref: '#/components/schemas/PersonalDataDocument'),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function export(Request $request, DispatchesCommands $dispatchesCommands): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $document = $dispatchesCommands->dispatch(new ExportPersonalDataCommand(new RiderId($user->id)));

        abort_unless(is_array($document), 500);

        return response()->streamDownload(function () use ($document): void {
            echo (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, 'traceway-data.json', [
            'Content-Type' => 'application/json',
        ]);
    }
}
