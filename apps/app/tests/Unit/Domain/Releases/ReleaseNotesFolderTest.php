<?php

declare(strict_types=1);

use App\Domain\Releases\Release;
use App\Domain\Releases\ReleaseNotesFolder;

const RELEASE_FILE = <<<'MD'
---
title: Rivers, and stops that load
summary: "Routes follow rivers, and stops load again."
date: 2026-10-20
---
MD;

it('merges the notes of each branch by section, New first', function (): void {
    $release = ReleaseNotesFolder::read('v0.8.0', RELEASE_FILE, [
        'stops.md' => "---\nsection: fixes\n---\n- **Stops load again.**\n",
        'rivers.md' => "---\nsection: New\n---\n- Routes can follow a river.\n",
        'garmin.md' => "---\nsection: Devices\n---\n- The watch shows the next stop.\n",
        'maps.md' => "---\nsection: New\n---\n- The map is sharper.\n",
        'roads.md' => "---\nsection: Quieter roads\n---\n- Fewer busy junctions.\n",
    ]);

    expect($release->tag)->toBe('v0.8.0')
        ->and($release->title)->toBe('Rivers, and stops that load')
        ->and($release->summary)->toBe('Routes follow rivers, and stops load again.')
        ->and($release->publishedAt?->format('Y-m-d H:i'))->toBe('2026-10-20 00:00')
        ->and($release->notes)->toBe(<<<'MD'
            ## New

            - The map is sharper.
            - Routes can follow a river.

            ## Fixes

            - **Stops load again.**

            ## Devices

            - The watch shows the next stop.

            ## Quieter roads

            - Fewer busy junctions.
            MD);
});

it('puts words written for the whole release first', function (): void {
    $release = ReleaseNotesFolder::read('v0.6.0', "---\ntitle: Stops\n---\n\nWater shows up again.\n\n## Stops that load\n\n- Fast.\n", []);

    expect($release->notes)->toBe("Water shows up again.\n\n## Stops that load\n\n- Fast.")
        ->and($release->summary)->toBeNull()
        ->and($release->publishedAt)->toBeNull();
});

it('reads a full date and time', function (): void {
    $release = ReleaseNotesFolder::read('v0.7.0', "---\ntitle: Bell\ndate: 2026-10-14T21:42:41Z\n---\n", ['bell.md' => "---\nsection: New\n---\n- A bell.\n"]);

    expect($release->publishedAt?->format(DATE_ATOM))->toBe('2026-10-14T21:42:41+00:00');
});

it('refuses notes it cannot read, and says which file and why', function (string $tag, string $releaseFile, array $branchNotes, string $message): void {
    expect(fn (): Release => ReleaseNotesFolder::read($tag, $releaseFile, $branchNotes))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'no front matter' => ['v0.8.0', "title: Rivers\n", [], 'v0.8.0/_release.md starts with front matter'],
    'no title' => ['v0.8.0', "---\nsummary: Rivers\n---\n", ['a.md' => "---\nsection: New\n---\n- A\n"], 'v0.8.0/_release.md needs a title.'],
    'no notes at all' => ['v0.8.0', "---\ntitle: Rivers\n---\n", [], 'v0.8.0 has no notes for riders.'],
    'a branch with no section' => ['v0.8.0', RELEASE_FILE, ['rivers.md' => "---\n---\n- A\n"], 'v0.8.0/rivers.md needs a section'],
    'a branch with no changes' => ['v0.8.0', RELEASE_FILE, ['rivers.md' => "---\nsection: New\n---\n\n"], 'v0.8.0/rivers.md has no changes'],
    'a bad date' => ['v0.8.0', "---\ntitle: Rivers\ndate: next week\n---\n", ['a.md' => "---\nsection: New\n---\n- A\n"], 'has a date that is not'],
    'a bad line' => ['v0.8.0', "---\ntitle Rivers\n---\n", [], 'not "key: value"'],
    'not a version' => ['v0.8', RELEASE_FILE, ['a.md' => "---\nsection: New\n---\n- A\n"], 'named by its version'],
]);

it('writes the release file a new version starts with', function (): void {
    expect(ReleaseNotesFolder::releaseFile("Rivers,\n and stops", '  Routes follow rivers. '))
        ->toBe("---\ntitle: Rivers, and stops\nsummary: Routes follow rivers.\n---\n")
        ->and(ReleaseNotesFolder::releaseFile('Rivers', summary: null))->toBe("---\ntitle: Rivers\n---\n");
});
