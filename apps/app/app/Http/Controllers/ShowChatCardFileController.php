<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShowChatCardFileController extends Controller
{
    private const array TYPES = [
        'mjs' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'woff2' => 'font/woff2',
    ];

    /**
     * MapLibre and the font for the map in a chat card, from Traceway, so no
     * third party learns who opens a card. The build keeps copies under
     * fixed names. A local site without a build serves MapLibre from its
     * packages, and the card falls back to the system font.
     */
    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        $path = collect([public_path('build/chat/'.$file), base_path('node_modules/maplibre-gl/dist/'.$file)])
            ->first(fn (string $candidate): bool => is_file($candidate));

        // The card asks from the chat app's own domain, so even a missing
        // file says so in a way it can read.
        abort_unless(is_string($path), 404, headers: ['Access-Control-Allow-Origin' => '*']);

        $binaryFileResponse = new BinaryFileResponse($path, headers: [
            'Content-Type' => self::TYPES[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ], autoEtag: true);

        $binaryFileResponse->isNotModified($request);

        return $binaryFileResponse;
    }
}
