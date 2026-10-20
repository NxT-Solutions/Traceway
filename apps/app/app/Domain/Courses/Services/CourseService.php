<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

use App\Domain\Connections\GrantedAccess;

/**
 * A service that keeps routes for a rider and passes them on to a device, such as Wahoo. The rider connects once,
 * which also logs them in. After that Traceway sends a course on request with the keys it keeps.
 */
interface CourseService extends GrantedAccess
{
    /**
     * Puts the course in the rider's library at the service, replacing the one it sent before.
     *
     * @throws ServiceRefused when the rider has to connect again
     * @throws ServiceUnavailable when the service cannot be reached or rejects the course
     */
    public function publish(ServiceTokens $serviceTokens, CourseUpload $courseUpload, ?string $previousId): PublishedCourse;

    /**
     * The rider's account at the service the keys belong to.
     *
     * @throws ServiceRefused when the rider has to connect again
     * @throws ServiceUnavailable when the service cannot be reached or does not say who the rider is
     */
    public function account(ServiceTokens $serviceTokens): ServiceAccount;
}
