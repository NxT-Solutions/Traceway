<?php

declare(strict_types=1);

namespace App\Domain\Courses;

/**
 * A route read from a file someone brought, before it is ridden again.
 */
final readonly class ImportedCourse
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function __construct(
        public string $name,
        public array $coordinates,
    ) {}
}
