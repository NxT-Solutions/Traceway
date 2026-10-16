<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

/**
 * The course as the service now holds it. The id is the service's own handle, passed back on the next publish
 * so the course is updated, not added twice. While the service is still reading the file, the id may stand
 * for that work; the service resolves it on the next publish.
 */
final readonly class PublishedCourse
{
    public function __construct(
        public string $id,
        public ?string $url = null,
        public bool $processing = false,
    ) {}
}
