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
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyController extends Controller
{
    public function edit(Request $request, PersonalDataQuery $personalDataQuery): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('settings/privacy', $personalDataQuery->page($user->id)->toArray());
    }

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
