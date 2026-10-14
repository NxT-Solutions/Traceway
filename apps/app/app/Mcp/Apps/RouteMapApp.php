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
 * Chat apps keep this page for the whole session. So the page only draws a
 * plain card, then loads the map script from Traceway each time it shows, and
 * a change to the map reaches the chat without a restart.
 */
#[Name(value: 'route-map')]
#[Title(value: 'Traceway route')]
#[Description(value: 'Draws a planned Traceway route in the chat, with links to the map and the device files.')]
#[Uri(value: 'ui://traceway/route-map')]
final class RouteMapApp extends AppResource
{
    /**
     * Where the map library and the map tiles come from.
     *
     * @var list<string>
     */
    private const array MAP_DOMAINS = ['https://unpkg.com', 'https://tiles.openfreemap.org'];

    public function handle(Request $request): Response
    {
        return Response::view('mcp.route-map-app', [
            'mapScript' => route('mcp.apps.route-map'),
            'showStatus' => app()->isLocal(),
        ]);
    }

    public function appMeta(): AppMeta
    {
        $traceway = rtrim(url('/'), '/');

        return AppMeta::make()
            ->prefersBorder(prefersBorder: false)
            ->csp(Csp::make()
                ->resourceDomains([$traceway, ...self::MAP_DOMAINS])
                ->connectDomains([$traceway, ...self::MAP_DOMAINS]));
    }
}
