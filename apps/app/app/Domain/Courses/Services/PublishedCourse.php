<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

/**
 * The course as the service now holds it. A service that is still processing the file has no id yet.
 */
final readonly class PublishedCourse
{
    public function __construct(
        public ?string $id,
        public ?string $url = null,
    ) {}

    public function processing(): bool
    {
        return $this->id === null;
    }
}
