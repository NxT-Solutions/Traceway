<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShowRouteMapScriptController extends Controller
{
    /**
     * The map in the chat. Chat apps keep the page that loads it for a whole
     * session, so this is checked again each time and a change shows at once.
     * The page lives on the chat app's own domain, so any origin may load it.
     */
    public function __invoke(Request $request): BinaryFileResponse
    {
        $binaryFileResponse = new BinaryFileResponse(resource_path('mcp/route-map.js'), headers: [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Access-Control-Allow-Origin' => '*',
        ], autoEtag: true);

        $binaryFileResponse->isNotModified($request);

        return $binaryFileResponse;
    }
}
