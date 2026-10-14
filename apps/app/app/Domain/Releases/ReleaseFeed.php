<?php

declare(strict_types=1);

namespace App\Domain\Releases;

/**
 * Where the rider notes of each version are kept.
 */
interface ReleaseFeed
{
    /**
     * Every released version with notes for riders, newest first.
     *
     * @return list<Release>
     */
    public function published(): array;

    /**
     * The branches whose notes are merged but not in a release yet, by file name.
     *
     * @return list<string>
     */
    public function unreleased(): array;
}
