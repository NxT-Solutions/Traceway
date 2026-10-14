<?php

declare(strict_types=1);

namespace App\Domain\Releases;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One published version of Traceway, with the notes a rider reads.
 */
final readonly class Release
{
    public function __construct(
        public string $tag,
        public string $name,
        public DateTimeImmutable $publishedAt,
        public string $notes,
    ) {
        throw_if(trim($this->tag) === '', InvalidArgumentException::class, 'A release has a tag.');
    }
}
