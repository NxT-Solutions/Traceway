<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Courses\Services\CourseService;
use App\Domain\Courses\Services\CourseUpload;
use App\Domain\Courses\Services\PublishedCourse;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use Illuminate\Support\Facades\Date;

/**
 * A service that keeps what it was sent, so a test can read it back.
 */
final class RecordingCourseService implements CourseService
{
    /** @var list<array{tokens: ServiceTokens, upload: CourseUpload, previous: string|null}> */
    public array $published = [];

    /** @var list<string> */
    public array $revoked = [];

    public int $refreshed = 0;

    /** 'refused' or 'unavailable' makes the next publish fail that way. */
    public ?string $failure = null;

    /** When false, the service is still reading the upload, as Ride with GPS can be. */
    public bool $ready = true;

    public function __construct(private readonly string $slug = 'wahoo') {}

    public function authorizationUrl(string $state): string
    {
        return 'https://'.$this->slug.'.test/oauth/authorize?state='.$state;
    }

    public function connect(string $code): ServiceTokens
    {
        throw_if($code === 'refused', ServiceRefused::class, 'The sign-in was turned down.');

        return new ServiceTokens('access-'.$code, 'refresh-'.$code, Date::now()->getTimestamp() + 7200);
    }

    public function refresh(ServiceTokens $serviceTokens): ServiceTokens
    {
        $this->refreshed++;

        return new ServiceTokens('access-refreshed', 'refresh-refreshed', Date::now()->getTimestamp() + 7200);
    }

    public function publish(ServiceTokens $serviceTokens, CourseUpload $courseUpload, ?string $previousId): PublishedCourse
    {
        throw_if($this->failure === 'refused', ServiceRefused::class, 'The token was withdrawn.');

        throw_if($this->failure === 'unavailable', ServiceUnavailable::class, 'The service is down.');

        $this->published[] = ['tokens' => $serviceTokens, 'upload' => $courseUpload, 'previous' => $previousId];

        $id = (string) (100 + count($this->published));

        if (! $this->ready) {
            return new PublishedCourse('task:'.$id, processing: true);
        }

        return new PublishedCourse($id, 'https://'.$this->slug.'.test/routes/'.$id);
    }

    public function revoke(ServiceTokens $serviceTokens): void
    {
        $this->revoked[] = $serviceTokens->accessToken;
    }
}
