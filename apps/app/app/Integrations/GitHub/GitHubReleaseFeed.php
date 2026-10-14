<?php

declare(strict_types=1);

namespace App\Integrations\GitHub;

use App\Domain\Releases\Release;
use App\Domain\Releases\ReleaseFeed;
use App\Domain\Releases\RiderNotes;
use App\Integrations\GitHub\Requests\ListReleasesRequest;
use DateTimeImmutable;
use Exception;
use RuntimeException;

final readonly class GitHubReleaseFeed implements ReleaseFeed
{
    public function __construct(
        private GitHubConnector $gitHubConnector,
        private string $repository,
    ) {}

    public function published(): array
    {
        $response = $this->gitHubConnector->send(new ListReleasesRequest($this->repository));

        throw_if($response->failed(), RuntimeException::class, 'GitHub did not list the releases.');

        $releases = [];

        foreach ($response->json() as $entry) {
            $release = is_array($entry) ? $this->release($entry) : null;

            if ($release instanceof Release) {
                $releases[] = $release;
            }
        }

        usort($releases, fn (Release $a, Release $b): int => $b->publishedAt <=> $a->publishedAt);

        return $releases;
    }

    /**
     * @param  array<mixed>  $entry
     */
    private function release(array $entry): ?Release
    {
        if (($entry['draft'] ?? false) === true || ($entry['prerelease'] ?? false) === true) {
            return null;
        }

        $tag = $entry['tag_name'] ?? null;
        $publishedAt = $entry['published_at'] ?? null;
        $name = $entry['name'] ?? null;
        $body = $entry['body'] ?? null;

        if (! is_string($tag) || $tag === '' || ! is_string($publishedAt)) {
            return null;
        }

        $notes = RiderNotes::from(is_string($body) ? $body : '');

        if ($notes === '') {
            return null;
        }

        try {
            $published = new DateTimeImmutable($publishedAt);
        } catch (Exception) {
            return null;
        }

        return new Release(
            $tag,
            is_string($name) && trim($name) !== '' ? trim($name) : $tag,
            $published,
            $notes,
        );
    }
}
