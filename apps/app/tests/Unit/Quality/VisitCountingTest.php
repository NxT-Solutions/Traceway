<?php

declare(strict_types=1);

/**
 * The site counts visits only after a yes, and the privacy policy names every page that asks.
 */
uses()->group('gdpr');

it('loads the visit counter only from the consent question', function (): void {
    $web = dirname(__DIR__, 5).'/apps/web/src';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($web, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = $file->getPathname();
        $source = file_get_contents($path) ?: '';

        if (str_contains($source, 'cloudflareinsights.com')) {
            expect($path)->toEndWith('/components/Consent.astro');
        }

        // Session recording was tried and dropped. The policy promises counts only.
        expect($source)->not->toContain('clarity.ms');
    }

    // Storybook runs on the live site under /storybook/ and shows the question in its stories. It never counts.
    expect(file_get_contents($web.'/components/Consent.astro'))
        ->toContain("location.hostname === 'traceway.online'")
        ->toContain("!location.pathname.startsWith('/storybook/')");
});

it('names every page that asks in the privacy policy', function (): void {
    $web = dirname(__DIR__, 5).'/apps/web/src';
    $legal = file_get_contents($web.'/legal.ts') ?: '';

    preg_match("/\\*\\*Where it runs:\\*\\*([^']+)'/", $legal, $where);
    expect($where)->toHaveKey(1);

    $asking = [];

    foreach (glob($web.'/pages/*.astro') ?: [] as $page) {
        $source = file_get_contents($page) ?: '';

        if (preg_match('/layouts\/(Base|V2)\.astro|components\/LegalPage\.astro/', $source) !== 1) {
            continue;
        }

        $name = basename($page, '.astro');
        $asking[] = $name === 'index' ? '/' : '/'.$name;
    }

    expect($asking)->not->toBeEmpty();

    foreach ($asking as $path) {
        expect($where[1])->toContain("({$path})");
    }
});
