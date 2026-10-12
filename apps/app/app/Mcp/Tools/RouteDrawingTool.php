<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Apps\RouteMapApp;
use App\Mcp\RouteLinks;
use App\Queries\Routes\RouteDrawingQuery;
use App\Routes\RouteId;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

/**
 * The line the in-chat map draws. Only the map calls it, so the model never
 * reads coordinates and never has to.
 */
#[Name(value: 'route_drawing')]
#[Title(value: 'Draw a saved route')]
#[Description(value: 'The line of a saved route, for the Traceway map in the chat.')]
#[IsReadOnly]
#[RendersApp(resource: RouteMapApp::class, visibility: [Visibility::App])]
final class RouteDrawingTool extends Tool
{
    use ReadsArguments;

    public function handle(Request $request, RouteDrawingQuery $routeDrawingQuery): Response|ResponseFactory
    {
        $riderId = $this->riderId($request);

        if ($riderId === null) {
            return Response::error('Sign in to Traceway first.');
        }

        $validated = $request->validate(['id' => ['required', 'uuid']]);

        try {
            $routeId = new RouteId($this->text($validated, 'id'));
        } catch (InvalidArgumentException) {
            return Response::error('That is not a Traceway route.');
        }

        $drawing = $routeDrawingQuery->owned($routeId, $riderId);

        if ($drawing === null) {
            return Response::error('That route is not in your library.');
        }

        return Response::structured([...$drawing, ...RouteLinks::for($drawing['id'])]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The id of a saved route.')
                ->required(),
        ];
    }
}
