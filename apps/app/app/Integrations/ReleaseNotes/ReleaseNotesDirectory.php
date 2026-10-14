<?php

declare(strict_types=1);

namespace App\Integrations\ReleaseNotes;

use App\Domain\Releases\Release;
use App\Domain\Releases\ReleaseFeed;
use App\Domain\Releases\ReleaseNotesFolder;
use InvalidArgumentException;
use RuntimeException;

/**
 * The rider notes in the repository, resources/releases/. Each branch adds one file to
 * unreleased/. A release moves them, unchanged, into a folder named by its version.
 */
final readonly class ReleaseNotesDirectory implements ReleaseFeed
{
    public const string UNRELEASED = 'unreleased';

    public function __construct(private string $directory) {}

    public function published(): array
    {
        $releases = [];

        foreach ($this->versionFolders() as $tag => $folder) {
            $releases[] = ReleaseNotesFolder::read(
                $tag,
                $this->contents($folder.'/'.ReleaseNotesFolder::RELEASE_FILE),
                $this->branchNotes($folder),
            );
        }

        usort($releases, fn (Release $a, Release $b): int => version_compare(ltrim($b->tag, 'v'), ltrim($a->tag, 'v')));

        return $releases;
    }

    public function unreleased(): array
    {
        return array_keys($this->branchNotes($this->directory.'/'.self::UNRELEASED));
    }

    /**
     * Moves every unreleased branch's notes into the new version's folder, beside a
     * _release.md with the title and summary riders see. Nothing is moved when one file
     * is not valid, or the version already has a folder.
     *
     * @return list<string> The files moved.
     */
    public function prepare(string $tag, string $title, ?string $summary): array
    {
        throw_if(preg_match(ReleaseNotesFolder::TAG, $tag) !== 1, InvalidArgumentException::class, 'A version looks like v1.4.0, not '.$tag.'.');

        $folder = $this->directory.'/'.$tag;
        throw_if(file_exists($folder), InvalidArgumentException::class, $tag.' already has rider notes in '.$folder.'.');

        $unreleased = $this->directory.'/'.self::UNRELEASED;
        $branchNotes = $this->branchNotes($unreleased);
        throw_if($branchNotes === [], InvalidArgumentException::class, 'There are no rider notes in '.$unreleased.' to release.');

        foreach ($branchNotes as $name => $contents) {
            ReleaseNotesFolder::branchNote($name, $contents);
        }

        $releaseFile = ReleaseNotesFolder::releaseFile($title, $summary);
        ReleaseNotesFolder::read($tag, $releaseFile, $branchNotes);

        throw_unless(mkdir($folder, 0775, recursive: true), RuntimeException::class, 'Could not create '.$folder.'.');
        throw_if(file_put_contents($folder.'/'.ReleaseNotesFolder::RELEASE_FILE, $releaseFile) === false, RuntimeException::class, 'Could not write the release file in '.$folder.'.');

        foreach (array_keys($branchNotes) as $name) {
            throw_unless(rename($unreleased.'/'.$name, $folder.'/'.$name), RuntimeException::class, 'Could not move '.$name.' into '.$folder.'.');
        }

        return array_keys($branchNotes);
    }

    /**
     * @return array<string, string> Each version's folder, by version.
     */
    private function versionFolders(): array
    {
        $folders = [];

        foreach ($this->glob($this->directory.'/v*', GLOB_ONLYDIR) as $folder) {
            $tag = basename($folder);

            throw_if(preg_match(ReleaseNotesFolder::TAG, $tag) !== 1, InvalidArgumentException::class, 'The folder '.$tag.' is not named by a version, such as v1.4.0.');

            $folders[$tag] = $folder;
        }

        return $folders;
    }

    /**
     * @return array<string, string> Each branch's notes, by file name.
     */
    private function branchNotes(string $folder): array
    {
        $notes = [];

        foreach ($this->glob($folder.'/*.md') as $file) {
            $name = basename($file);

            if ($name !== ReleaseNotesFolder::RELEASE_FILE) {
                $notes[$name] = $this->contents($file);
            }
        }

        ksort($notes);

        return $notes;
    }

    /**
     * @return list<string>
     */
    private function glob(string $pattern, int $flags = 0): array
    {
        $found = glob($pattern, $flags);

        return $found === false ? [] : $found;
    }

    private function contents(string $file): string
    {
        throw_unless(is_file($file), InvalidArgumentException::class, $file.' is missing.');

        $contents = file_get_contents($file);

        throw_if($contents === false, RuntimeException::class, 'Could not read '.$file.'.');

        return $contents;
    }
}
