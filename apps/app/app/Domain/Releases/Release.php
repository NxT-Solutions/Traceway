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
        throw_if(preg_match(ReleaseNotesFolder::TAG, $this->tag) !== 1, InvalidArgumentException::class, 'A release is named by its version, such as v1.4.0.');
        throw_if(trim($this->title) === '', InvalidArgumentException::class, 'A release has a title.');
        throw_if(trim($this->notes) === '', InvalidArgumentException::class, 'A release has notes.');
    }
}
