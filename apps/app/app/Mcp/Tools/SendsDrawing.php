<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Apps\RouteMapApp;
use App\Queries\Routes\RouteDrawingQuery;
use App\Routes\RouteId;
use Laravel\Mcp\ResponseFactory;

/**
 * Sends the line along with a plan, for the map in the chat. It goes in the
 * result's _meta, which the map reads and the model does not, so the map can
 * draw without asking the server again. Some hosts never pass that second
 * call on.
 */
trait SendsDrawing
{
    private function withDrawing(ResponseFactory $responseFactory, RouteDrawingQuery $routeDrawingQuery, string $id, int $riderId): ResponseFactory
    {
        $drawing = $routeDrawingQuery->owned(new RouteId($id), $riderId);

        return $drawing === null ? $responseFactory : $responseFactory->withMeta(RouteMapApp::DRAWING, $drawing);
    }
}
