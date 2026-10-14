<?php

declare(strict_types=1);

use App\Domain\Releases\Release;
use App\Integrations\GitHub\GitHubConnector;
use App\Integrations\GitHub\GitHubReleaseFeed;
use App\Integrations\GitHub\Requests\ListReleasesRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function githubRelease(string $tag, string $publishedAt, string $body, bool $draft = false, bool $prerelease = false, ?string $name = null): array
{
    return [
        'tag_name' => $tag,
        'name' => $name ?? $tag,
        'draft' => $draft,
        'prerelease' => $prerelease,
        'published_at' => $publishedAt,
        'body' => $body,
    ];
}

it('reads the published releases newest first, with the notes a rider reads', function (): void {
    $mock = new MockClient([
        ListReleasesRequest::class => MockResponse::make([
            githubRelease('v0.1.0', '2026-10-01T10:00:00Z', "## What's Changed\n### New\n* feat: first by @a in https://github.com/x/y/pull/1"),
            githubRelease('v0.2.0', '2026-10-10T10:00:00Z', "## What's Changed\n### Fixes\n* fix: second by @a in https://github.com/x/y/pull/2", name: 'Stops along the way'),
        ]),
    ]);
    $connector = new GitHubConnector('https://github.test');
    $connector->withMockClient($mock);

    $releases = new GitHubReleaseFeed($connector, 'NxT-Solutions/Traceway')->published();

    expect($releases)->toHaveCount(2)
        ->and($releases[0]->tag)->toBe('v0.2.0')
        ->and($releases[0]->name)->toBe('Stops along the way')
        ->and($releases[0]->notes)->toBe("## Fixes\n\n* Second")
        ->and($releases[1]->name)->toBe('v0.1.0');

    $mock->assertSent(fn (ListReleasesRequest $listReleasesRequest, Response $response): bool => $response->getPendingRequest()->getUrl() === 'https://github.test/repos/NxT-Solutions/Traceway/releases'
        && $response->getPendingRequest()->query()->get('per_page') === 30
        && str_starts_with((string) $response->getPendingRequest()->headers()->get('User-Agent'), 'Traceway/')
        && $response->getPendingRequest()->headers()->get('Authorization') === null);
});

it('leaves out drafts, previews, and releases with nothing for a rider', function (): void {
    $connector = new GitHubConnector('https://github.test');
    $connector->withMockClient(new MockClient([
        ListReleasesRequest::class => MockResponse::make([
            githubRelease('v0.4.0', '2026-10-12T10:00:00Z', "### New\n* Draft", draft: true),
            githubRelease('v0.4.0-rc.1', '2026-10-11T10:00:00Z', "### New\n* Preview", prerelease: true),
            githubRelease('v0.3.1', '2026-10-10T10:00:00Z', "### Dependencies\n* Bump vite"),
            githubRelease('v0.3.0', '2026-10-09T10:00:00Z', "### New\n* Kept"),
        ]),
    ]));

    $releases = new GitHubReleaseFeed($connector, 'NxT-Solutions/Traceway')->published();

    expect(array_map(fn (Release $release): string => $release->tag, $releases))->toBe(['v0.3.0']);
});

it('sends the token when there is one', function (): void {
    $mock = new MockClient([ListReleasesRequest::class => MockResponse::make([])]);
    $connector = new GitHubConnector('https://github.test', 'secret-token');
    $connector->withMockClient($mock);

    new GitHubReleaseFeed($connector, 'NxT-Solutions/Traceway')->published();

    $mock->assertSent(fn (ListReleasesRequest $listReleasesRequest, Response $response): bool => $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer secret-token');
});

it('fails loudly when GitHub is down', function (): void {
    $connector = new GitHubConnector('https://github.test');
    $connector->withMockClient(new MockClient([ListReleasesRequest::class => MockResponse::make(['message' => 'busy'], 503)]));

    new GitHubReleaseFeed($connector, 'NxT-Solutions/Traceway')->published();
})->throws(RuntimeException::class, 'GitHub did not list the releases.');
