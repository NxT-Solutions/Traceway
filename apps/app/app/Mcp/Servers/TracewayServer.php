<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Apps\RouteMapApp;
use App\Mcp\Methods\CountedCallTool;
use App\Mcp\Tools\ListRoutesTool;
use App\Mcp\Tools\PlanLoopTool;
use App\Mcp\Tools\PlanMultisportTool;
use App\Mcp\Tools\RouteDrawingTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Override;

#[Name(value: 'Traceway')]
#[Version(value: '1.0.0')]
#[Instructions(value: <<<'TEXT'
    Traceway plans loop routes for running, trail running, walking, hiking, road cycling, gravel, and mountain biking, and multisport sessions (brick, triathlon, duathlon, aquabike). Every route comes from OpenStreetMap roads and paths through a routing engine. Never invent coordinates for a route; call a tool.

    - Ask for the start and the distance if the person has not said them. A town with its country works best.
    - Use plan_loop for one sport, and plan_multisport for a brick, triathlon, duathlon, or aquabike.
    - Each plan is saved to the person's Traceway library. Share the map link, then the Garmin file or the GPX file for other brands.
    - A swim is never routed. Tell the person to draw it in the planner.
    - Keep answers short and plain. Distances are in kilometres.
    TEXT)]
final class TracewayServer extends Server
{
    #[Override]
    protected array $tools = [
        PlanLoopTool::class,
        PlanMultisportTool::class,
        ListRoutesTool::class,
        RouteDrawingTool::class,
    ];

    #[Override]
    protected array $resources = [
        RouteMapApp::class,
    ];

    /**
     * Each tool call adds to that tool's daily total.
     */
    #[Override]
    protected function boot(): void
    {
        $this->addMethod('tools/call', CountedCallTool::class);
    }
}
