<?php

declare(strict_types=1);

use App\Domain\Releases\Release;
use App\Integrations\ReleaseNotes\ReleaseNotesDirectory;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->releases = storage_path('framework/testing/releases-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($this->releases.'/unreleased');
    File::put($this->releases.'/unreleased/.gitkeep', '');
});

afterEach(function (): void {
    File::deleteDirectory($this->releases);
});

function releaseNote(string $folder, string $name, string $section, string $change): void
{
    File::ensureDirectoryExists($folder);
    File::put($folder.'/'.$name, "---\nsection: {$section}\n---\n- {$change}\n");
}

it('reads every version folder, newest version first', function (): void {
    File::ensureDirectoryExists($this->releases.'/v0.10.0');
    File::put($this->releases.'/v0.10.0/_release.md', "---\ntitle: Ten\n---\n");
    releaseNote($this->releases.'/v0.10.0', 'ten.md', 'New', 'Ten');
    File::ensureDirectoryExists($this->releases.'/v0.9.0');
    File::put($this->releases.'/v0.9.0/_release.md', "---\ntitle: Nine\n---\n\nNine, written for the whole release.\n");

    $releases = new ReleaseNotesDirectory($this->releases)->published();

    expect(array_map(fn (Release $release): string => $release->tag, $releases))->toBe(['v0.10.0', 'v0.9.0'])
        ->and($releases[0]->notes)->toBe("## New\n\n- Ten");
});

it('lists the notes that are merged but not released', function (): void {
    releaseNote($this->releases.'/unreleased', 'rivers.md', 'New', 'Rivers');
    releaseNote($this->releases.'/unreleased', 'stops.md', 'Fixes', 'Stops');

    expect(new ReleaseNotesDirectory($this->releases)->unreleased())->toBe(['rivers.md', 'stops.md']);
});

it('moves the unreleased notes, unchanged, into the new version and leaves unreleased empty', function (): void {
    releaseNote($this->releases.'/unreleased', 'rivers.md', 'New', 'Rivers');
    releaseNote($this->releases.'/unreleased', 'stops.md', 'Fixes', 'Stops');
    $before = File::get($this->releases.'/unreleased/rivers.md');

    $moved = new ReleaseNotesDirectory($this->releases)->prepare('v0.8.0', 'Rivers and stops', 'Routes follow rivers.');

    expect($moved)->toBe(['rivers.md', 'stops.md'])
        ->and(File::get($this->releases.'/v0.8.0/rivers.md'))->toBe($before)
        ->and(File::get($this->releases.'/v0.8.0/_release.md'))->toBe("---\ntitle: Rivers and stops\nsummary: Routes follow rivers.\n---\n")
        ->and(new ReleaseNotesDirectory($this->releases)->unreleased())->toBe([])
        ->and(File::exists($this->releases.'/unreleased/.gitkeep'))->toBeTrue()
        ->and(new ReleaseNotesDirectory($this->releases)->published()[0]->notes)->toBe("## New\n\n- Rivers\n\n## Fixes\n\n- Stops");
});

it('moves nothing when a note is broken, the version exists, or there is nothing to release', function (string $setup, string $message): void {
    if ($setup === 'broken') {
        releaseNote($this->releases.'/unreleased', 'rivers.md', 'New', 'Rivers');
        File::put($this->releases.'/unreleased/stops.md', "- no front matter\n");
    }

    if ($setup === 'exists') {
        releaseNote($this->releases.'/unreleased', 'rivers.md', 'New', 'Rivers');
        File::ensureDirectoryExists($this->releases.'/v0.8.0');
    }

    expect(fn (): array => new ReleaseNotesDirectory($this->releases)->prepare('v0.8.0', 'Rivers', summary: null))->toThrow(InvalidArgumentException::class, $message);

    if ($setup !== 'empty') {
        expect(File::exists($this->releases.'/unreleased/rivers.md'))->toBeTrue();
    }
})->with([
    'a broken note' => ['broken', 'stops.md starts with front matter'],
    'the version exists' => ['exists', 'v0.8.0 already has rider notes'],
    'nothing to release' => ['empty', 'There are no rider notes'],
]);

it('keeps to versions like v1.4.0', function (): void {
    releaseNote($this->releases.'/unreleased', 'rivers.md', 'New', 'Rivers');

    expect(fn (): array => new ReleaseNotesDirectory($this->releases)->prepare('0.8', 'Rivers', summary: null))->toThrow(InvalidArgumentException::class, 'A version looks like v1.4.0');
});
