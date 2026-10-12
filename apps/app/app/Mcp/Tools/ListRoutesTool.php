<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\RouteLinks;
use App\Queries\Routes\LibraryQuery;
use App\Queries\Routes\LibraryRoute;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name(value: 'list_routes')]
#[Title(value: 'List saved routes')]
#[Description(value: "Lists the routes saved in the person's Traceway library, newest first, with links to the map and the files for a watch or bike computer.")]
#[IsReadOnly]
final class ListRoutesTool extends Tool
{
    use ReadsArguments;

    public function handle(Request $request, LibraryQuery $libraryQuery): Response|ResponseFactory
    {
        $riderId = $this->riderId($request);

        if ($riderId === null) {
            return Response::error('Sign in to Traceway first.');
        }

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) $this->number($validated, 'limit');
        $limit = $limit > 0 ? $limit : 10;

        $routes = array_slice($libraryQuery->forRider($riderId), 0, $limit);

        if ($routes === []) {
            return Response::text('The library is empty. Plan a loop first.');
        }

        return Response::structured([
            'routes' => array_map(fn (LibraryRoute $libraryRoute): array => [
                'id' => $libraryRoute->id->value,
                'name' => $libraryRoute->name,
                'sport' => $libraryRoute->layout === null ? $libraryRoute->sport : 'multisport',
                'distance_km' => RouteLinks::kilometres($libraryRoute->distanceMetres),
                'saved_at' => $libraryRoute->createdAt,
                ...RouteLinks::for($libraryRoute->id->value),
            ], $routes),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->description('How many routes to list. Defaults to 10.'),
        ];
    }
}
