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

it("has room for the planner's headers, which pass nginx's 4 KB default", function (): void {
    $config = file_get_contents(dirname(__DIR__, 4).'/.docker/production/images/configs/nginx/nginx.conf') ?: '';

    preg_match('/location ~ \\\\\.php\$ \{([^}]*)\}/', $config, $block);

    expect($block)->toHaveKey(1)
        ->and($block[1])->toContain('fastcgi_buffer_size 32k;');
});

it('sends text compressed and keeps the hashed build files for a year', function (): void {
    $config = file_get_contents(dirname(__DIR__, 4).'/.docker/production/images/configs/nginx/nginx.conf') ?: '';

    expect($config)
        ->toContain('gzip on;')
        ->toMatch('/gzip_types[^;]*application\/javascript[^;]*text\/css/');

    foreach (['/build/assets/', '/_astro/'] as $path) {
        preg_match('/location \^~ '.preg_quote($path, '/').' \{([^}]*)\}/', $config, $block);

        expect($block)->toHaveKey(1)
            ->and($block[1])->toContain('add_header Cache-Control "public, max-age=31536000, immutable";')
            ->and($block[1])->not->toContain('expires');
    }
});

it("serves security.txt from the content site, and leaves the rest of .well-known to Laravel", function (): void {
    $config = file_get_contents(dirname(__DIR__, 4).'/.docker/production/images/configs/nginx/nginx.conf') ?: '';
    $source = file_get_contents(dirname(__DIR__, 4).'/apps/web/src/pages/security.txt.ts') ?: '';

    preg_match('/location = \/\.well-known\/security\.txt \{([^}]*)\}/', $config, $block);

    expect($block)->toHaveKey(1)
        ->and($block[1])->toContain('try_files /security.txt =404;')
        ->and($source)->toContain('Contact: ')
        ->and($source)->toContain('Expires: ');
});
