<?php

declare(strict_types=1);

namespace App\Domain\Releases;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One version of Traceway, with the notes a rider reads. A release with no date is
 * dated the first time a deploy reads it, which is when riders get it.
 */
final readonly class Release
{
    public function __construct(
        public string $tag,
        public string $title,
        public ?string $summary,
        public string $notes,
        public ?DateTimeImmutable $publishedAt,
    ) {
        if (preg_match(ReleaseNotesFolder::TAG, $this->tag) !== 1) {
            throw new InvalidArgumentException('A release is named by its version, such as v1.4.0.');
        }

        if (trim($this->title) === '') {
            throw new InvalidArgumentException('A release has a title.');
        }

        if (trim($this->notes) === '') {
            throw new InvalidArgumentException('A release has notes.');
        }
    }
}
