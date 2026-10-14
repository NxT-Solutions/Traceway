<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Queries\Routes\RouteDrawingQuery;
use App\Queries\Routes\SignedRouteLink;
use App\Routes\RouteId;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * The answer to a plan. The model reads the text: the name, the distance,
 * and the links. The card in the chat reads the structured content, which
 * also carries the line to draw and the map page to show over it.
 */
trait AnswersWithDrawing
{
    /**
     * @param  array<string, mixed>  $plan
     */
    private function withDrawing(array $plan, RouteDrawingQuery $routeDrawingQuery, string $id, int $riderId): ResponseFactory
    {
        $drawing = $routeDrawingQuery->owned(new RouteId($id), $riderId);
        $text = json_encode($plan, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new ResponseFactory(Response::text($text))
            ->withStructuredContent([
                ...$plan,
                'drawing' => $drawing,
                'embed_url' => SignedRouteLink::embed($id),
            ]);
    }
}
