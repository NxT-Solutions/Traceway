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
     * Asks the service to withdraw Traceway's access. A token that ran out is refreshed first, or the service
     * would refuse the request and keep the access.
     *
     * @throws ServiceRefused when the service no longer accepts the token
     * @throws ServiceUnavailable when the service cannot be reached
     */
    public function withdraw(string $slug, ServiceTokens $serviceTokens, int $now): void
    {
        $courseService = $this->for($slug);

        if ($serviceTokens->expiresBy($now)) {
            $serviceTokens = $courseService->refresh($serviceTokens);
        }

        $courseService->revoke($serviceTokens);
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->services);
    }
}
