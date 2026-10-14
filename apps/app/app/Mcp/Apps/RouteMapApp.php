<?php

declare(strict_types=1);

namespace App\Mcp\Apps;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Csp;

/**
 * The route, drawn in the chat after a plan: the line, the start, each leg in
 * its own colour, and the links to take it to a watch.
 *
 * Chat apps keep this page for the whole session, and most do not let it run
 * a map. So it draws the line itself, then lays the map from Traceway's own
 * share page over it. That page is always the current one, so a change to the
 * map reaches the chat without a restart.
 */
#[Name(value: 'route-map')]
#[Title(value: 'Traceway route')]
#[Description(value: 'Draws a planned Traceway route in the chat, with links to the map and the device files.')]
#[Uri(value: 'ui://traceway/route-map')]
final class RouteMapApp extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::view('mcp.route-map-app', [
            'showStatus' => app()->isLocal(),
            'traceway' => $this->traceway(),
        ]);
    }

    public function appMeta(): AppMeta
    {
        return AppMeta::make()
            ->prefersBorder(prefersBorder: false)
            ->csp(Csp::make()->frameDomains([$this->traceway()]));
    }

    private function traceway(): string
    {
        return rtrim(url('/'), '/');
    }
}
