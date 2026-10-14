<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Queries\Routes\RouteDrawingQuery;
use App\Routes\RouteId;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * The answer to a plan. The model reads the text: the name, the distance,
 * and the links. The map in the chat reads the structured content, which
 * also carries the line, so it draws without asking the server again.
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
            ->withStructuredContent($drawing === null ? $plan : [...$plan, 'drawing' => $drawing]);
    }
}
