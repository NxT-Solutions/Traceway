<?php

declare(strict_types=1);

namespace App\Mcp\Apps;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

/**
 * The route, drawn in the chat after a plan: the line, the start, each leg in
 * its own colour, and the links to take it to a watch.
 */
#[Name(value: 'route-map')]
#[Title(value: 'Traceway route')]
#[Description(value: 'Draws a planned Traceway route in the chat, with links to the map and the device files.')]
#[Uri(value: 'ui://traceway/route-map')]
#[AppMeta(prefersBorder: false)]
final class RouteMapApp extends AppResource
{
    /**
     * Where a plan's result carries the line for the map.
     */
    public const string DRAWING = 'traceway/drawing';

    public function handle(Request $request): Response
    {
        return Response::view('mcp.route-map-app');
    }
}
