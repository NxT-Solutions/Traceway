<?php

declare(strict_types=1);

namespace App\Queries\Routes;

final readonly class LibraryPage
{
    /**
     * @param  list<LibraryRoute>  $routes
     */
    public function __construct(public array $routes) {}

    /**
     * @return array{routes: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'routes' => array_map(
                fn (LibraryRoute $libraryRoute): array => $libraryRoute->toArray(),
                $this->routes,
            ),
        ];
    }
}
