<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * In production nginx serves the content site and hands only the planner paths to Laravel.
 * A route whose first path part is missing there answers with the site's 404 page.
 */
it('hands every planner route to Laravel in production', function (): void {
    $config = file_get_contents(dirname(__DIR__, 4).'/.docker/production/images/configs/nginx/nginx.conf') ?: '';

    preg_match('/location ~ \^\/\(([^)]+)\)\(\/\|\$\)/', $config, $paths);
    expect($paths)->toHaveKey(1);

    $handed = array_map(stripslashes(...), explode('|', $paths[1]));

    $missing = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route): string => explode('/', $route->uri())[0])
        ->reject(fn (string $first): bool => $first === '' || in_array($first, $handed, strict: true))
        ->unique()
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
