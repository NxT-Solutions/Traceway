<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

/**
 * A service that keeps routes for a rider and passes them on to a device, such as Wahoo.
 * The rider connects once with OAuth. After that Traceway sends a course on request.
 */
interface CourseService
{
    /**
     * Where to send the rider to allow Traceway. The state comes back on the callback.
     */
    public function authorizationUrl(string $state): string;

    /**
     * Trades the code from the callback for tokens.
     *
     * @throws ServiceRefused when the code is not accepted
     * @throws ServiceUnavailable when the service cannot be reached
     */
    public function connect(string $code): ServiceTokens;

    /**
     * New tokens for ones that ran out. A service without refresh tokens returns the same ones.
     *
     * @throws ServiceRefused when the rider has to connect again
     * @throws ServiceUnavailable when the service cannot be reached
     */
    public function refresh(ServiceTokens $serviceTokens): ServiceTokens;

    /**
     * Puts the course in the rider's library at the service, replacing the one it sent before.
     *
     * @throws ServiceRefused when the rider has to connect again
     * @throws ServiceUnavailable when the service cannot be reached or rejects the course
     */
    public function publish(ServiceTokens $serviceTokens, CourseUpload $courseUpload, ?string $previousId): PublishedCourse;

    /**
     * Withdraws Traceway's access. A failure here does not stop the rider from disconnecting.
     */
    public function revoke(ServiceTokens $serviceTokens): void;
}
