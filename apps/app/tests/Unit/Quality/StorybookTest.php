<?php

declare(strict_types=1);

/**
 * Storybook is published on the live site, at /storybook/, next to the planner and the content site. A story runs
 * with a visitor's cookies and storage there, so it must never reach the real server or touch the visitor's choices.
 */
function storybookFile(string $path): string
{
    return file_get_contents(dirname(__DIR__, 5).'/'.$path) ?: '';
}

it('gives every story storage of its own before anything else runs', function (string $preview): void {
    $source = storybookFile($preview);

    preg_match('/^import [^;]+;/m', $source, $first);

    expect($first[0] ?? '')->toBe("import './own-storage';");
})->with(['apps/app/.storybook/preview.tsx', 'apps/web/.storybook/preview.ts']);

it('keeps the two Storybooks on the same storage rules', function (): void {
    $app = storybookFile('apps/app/.storybook/own-storage.ts');
    $web = storybookFile('apps/web/.storybook/own-storage.ts');
    $code = fn (string $source): string => preg_replace('/^\/\/.*\n/m', '', $source) ?? '';

    expect($app)->toContain("pathname.startsWith('/storybook/')")
        ->toContain("prefix = 'storybook:'")
        ->toContain('path=/storybook/')
        ->and($code($web))->toBe($code($app));
});

it('answers a request no story mocks in the browser, so it never reaches the planner', function (): void {
    $preview = storybookFile('apps/app/.storybook/preview.tsx');

    expect($preview)->toContain('setupWorker(noRealServer())')
        ->toContain('{ status: 503 }');
});
