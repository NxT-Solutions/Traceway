<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

use InvalidArgumentException;

/**
 * The services this Traceway can send to, by slug. A service without credentials is left out.
 */
final readonly class CourseServices
{
    /**
     * @param  array<string, CourseService>  $services
     */
    public function __construct(private array $services) {}

    public function has(string $slug): bool
    {
        return isset($this->services[$slug]);
    }

    public function for(string $slug): CourseService
    {
        return $this->services[$slug] ?? throw new InvalidArgumentException('No course service is set up for '.$slug.'.');
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->services);
    }
}
