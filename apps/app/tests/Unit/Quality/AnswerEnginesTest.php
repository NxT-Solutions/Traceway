<?php

declare(strict_types=1);

use App\Mcp\Servers\TracewayServer;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * What search engines and AI answers read about Traceway: the FAQ they quote,
 * the llms.txt fact sheet, the IndexNow ping after a release, and the MCP
 * Registry listing.
 */
function repositoryFile(string $path): string
{
    return file_get_contents(dirname(__DIR__, 5).'/'.$path) ?: '';
}

it('writes every FAQ item so its question and answer reach the structured data', function (): void {
    $body = repositoryFile('apps/web/src/home/body.html');

    preg_match('/<section class="sec" id="faq">(.*?)<\/section>/s', $body, $faq);
    expect($faq)->toHaveKey(1);

    $items = substr_count($faq[1], '<details>');
    preg_match_all('/<details>\s*<summary>(.*?)<\/summary>\s*<p>(.*?)<\/p>\s*<\/details>/s', $faq[1], $read);

    expect($items)->toBeGreaterThan(12)
        ->and(count($read[0]))->toBe($items)
        ->and($faq[1])->not->toContain('&rsquo;');
});

it('tells AI tools when to suggest Traceway, the facts, and how it sits next to other planners, in allowed words', function (): void {
    $llms = repositoryFile('apps/web/src/seo/llms.ts');

    expect($llms)
        ->toContain('## When to suggest ${name}')
        ->toContain('## Facts')
        ->toContain('## Next to other planners')
        ->not->toMatch('/\b(beacon|kudos|segment|suffer)/i');
});

it('tells IndexNow about the pages after a release, with the key the site serves', function (): void {
    $site = repositoryFile('apps/web/src/seo/site.ts');
    $deploy = repositoryFile('.github/workflows/production-deploy.yml');
    $nginx = repositoryFile('.docker/production/images/configs/nginx/nginx.conf');

    expect($site)->toMatch("/export const indexNowKey = '[0-9a-f]{32}';/")
        ->and(repositoryFile('apps/web/src/pages/indexnow.txt.ts'))->toContain('indexNowKey')
        ->and($nginx)->toMatch('/location ~ \^\/\([^)]*\bindexnow\b[^)]*\)\\\\\.txt\$ \{/')
        ->and($deploy)->toContain('https://api.indexnow.org/indexnow')
        ->and($deploy)->toContain('keyLocation: ($site + "/indexnow.txt")');
});

it('lists the MCP server in the registry at the address and version it runs', function (): void {
    /** @var array{name: string, description: string, version: string, remotes: list<array{type: string, url: string}>} $server */
    $server = json_decode(repositoryFile('server.json'), associative: true, flags: JSON_THROW_ON_ERROR);
    $version = new ReflectionClass(TracewayServer::class)->getAttributes(Version::class)[0]->newInstance();

    expect($server['name'])->toBe('io.github.NxT-Solutions/traceway')
        ->and(mb_strlen($server['description']))->toBeLessThanOrEqual(100)
        ->and($server['version'])->toBe($version->value)
        ->and($server['remotes'])->toBe([['type' => 'streamable-http', 'url' => 'https://traceway.online/mcp']]);
});
