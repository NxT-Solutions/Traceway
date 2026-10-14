<?php

declare(strict_types=1);

use App\Domain\Releases\ReleaseNotesFolder;
use App\Integrations\ReleaseNotes\ReleaseNotesDirectory;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The rider notes in the repository are what riders read after the next deploy. A file
 * that does not read here would leave the bell without that release.
 */
it('reads every released version in resources/releases', function (): void {
    $folders = glob(resource_path('releases').'/v*', GLOB_ONLYDIR);
    $releases = new ReleaseNotesDirectory(resource_path('releases'))->published();

    expect($releases)->not->toBeEmpty()->toHaveCount(is_array($folders) ? count($folders) : 0);
});

it('reads every rider note waiting in unreleased/', function (): void {
    $files = glob(resource_path('releases/unreleased').'/*');
    $files = is_array($files) ? $files : [];

    $notes = array_values(array_filter($files, fn (string $file): bool => str_ends_with($file, '.md')));

    $sections = array_map(
        fn (string $file): string => ReleaseNotesFolder::branchNote(basename($file), (string) file_get_contents($file))[0],
        $notes,
    );

    expect(array_map(basename(...), array_diff($files, $notes)))->toBe([])
        ->and($sections)->each->not->toBeEmpty();
});
