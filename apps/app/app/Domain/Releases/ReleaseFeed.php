<?php

declare(strict_types=1);

namespace App\Domain\Releases;

/**
 * Where Traceway's published versions are announced.
 */
interface ReleaseFeed
{
    /**
     * The published releases with notes a rider can read, newest first. Drafts, previews,
     * and releases with nothing for a rider are left out.
     *
     * @return list<Release>
     */
    public function published(): array;
}
