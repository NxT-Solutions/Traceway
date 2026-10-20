<?php

declare(strict_types=1);

namespace App\Integrations\RideWithGps;

use App\Domain\Courses\Services\CourseService;
use App\Domain\Courses\Services\CourseUpload;
use App\Domain\Courses\Services\PublishedCourse;
use App\Domain\Courses\Services\ServiceAccount;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use App\Integrations\RideWithGps\Requests\DeleteRouteRequest;
use App\Integrations\RideWithGps\Requests\GetCurrentUserRequest;
use App\Integrations\RideWithGps\Requests\GetTaskRequest;
use App\Integrations\RideWithGps\Requests\RevokeTokenRequest;
use App\Integrations\RideWithGps\Requests\UploadRouteRequest;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Puts a course in the rider's Ride with GPS library as GPX. The rider sends it on to a device with Ride with GPS's Send to Device.
 */
final readonly class RideWithGpsCourseService implements CourseService
{
    private const string PENDING = 'task:';

    /**
     * @param  int  $pauseMilliseconds  how long to wait between looks at the upload task
     */
    public function __construct(
        private RideWithGpsConnector $rideWithGpsConnector,
        private int $attempts = 8,
        private int $pauseMilliseconds = 750,
    ) {}

    public function refresh(ServiceTokens $serviceTokens): ServiceTokens
    {
        return $serviceTokens;
    }

    /**
     * Uploads the new copy first and only then deletes the one sent before, so a failed upload leaves the
     * rider's route in place, on their device too. An upload still being read is kept as "task:{id}" and looked up next time.
     */
    public function publish(ServiceTokens $serviceTokens, CourseUpload $courseUpload, ?string $previousId): PublishedCourse
    {
        $previousRoute = $previousId === null ? null : $this->routeFor($previousId, $serviceTokens);

        $response = $this->send(new UploadRouteRequest(
            $courseUpload->gpx,
            $this->filename($courseUpload->title),
            $courseUpload->title,
            'Planned on Traceway',
        ), $serviceTokens);

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        $task = $response->json('task');
        $taskId = is_array($task) ? ($task['id'] ?? null) : null;

        throw_if(! is_array($task) || (! is_int($taskId) && ! is_string($taskId)), ServiceUnavailable::class, 'Ride with GPS did not say how to follow the upload.');

        // Ride with GPS took the new copy, so the old one can go.
        if ($previousRoute !== null) {
            try {
                $this->send(new DeleteRouteRequest($previousRoute), $serviceTokens);
            } catch (ServiceRefused|ServiceUnavailable) {
                // The new copy is in; an old one left behind is the lesser harm.
            }
        }

        return $this->follow((string) $taskId, $task, $serviceTokens);
    }

    public function account(ServiceTokens $serviceTokens): ServiceAccount
    {
        $response = $this->send(new GetCurrentUserRequest, $serviceTokens);

        throw_unless($response->successful(), ServiceUnavailable::class, 'Ride with GPS did not say who the rider is ('.$response->status().').');

        $user = $response->json('user');
        $id = is_array($user) ? ($user['id'] ?? null) : null;
        $email = is_array($user) ? ($user['email'] ?? null) : null;

        throw_if((! is_int($id) && ! is_string($id)) || $id === '', ServiceUnavailable::class, 'Ride with GPS did not say who the rider is.');

        return new ServiceAccount((string) $id, is_string($email) && $email !== '' ? $email : null);
    }

    public function revoke(ServiceTokens $serviceTokens): void
    {
        try {
            $this->rideWithGpsConnector->send(new RevokeTokenRequest(
                $this->rideWithGpsConnector->clientId(),
                $this->rideWithGpsConnector->clientSecret(),
                $serviceTokens->accessToken,
            ));
        } catch (FatalRequestException) {
            // The rider is disconnecting either way.
        }
    }

    /**
     * Ride with GPS ends one key at a time, and its keys never run out. A key a new log in replaced would
     * otherwise stay live where Traceway can no longer reach it.
     */
    public function supersede(ServiceTokens $serviceTokens): void
    {
        $this->revoke($serviceTokens);
    }

    /**
     * The route id behind an id from an earlier publish. An upload that was still being read is looked up;
     * if it is still not done, or failed, there is nothing to delete.
     */
    private function routeFor(string $previousId, ServiceTokens $serviceTokens): ?string
    {
        if (! str_starts_with($previousId, self::PENDING)) {
            return $previousId;
        }

        $response = $this->send(new GetTaskRequest(substr($previousId, strlen(self::PENDING))), $serviceTokens);
        $task = $response->json('task');

        if (! $response->successful() || ! is_array($task) || ($task['status'] ?? null) !== 'completed') {
            return null;
        }

        try {
            return $this->created($task)->id;
        } catch (ServiceUnavailable) {
            return null;
        }
    }

    /**
     * Waits a few seconds for the upload to be read. A slow one is reported as still processing.
     *
     * @param  array<array-key, mixed>  $task
     */
    private function follow(string $taskId, array $task, ServiceTokens $serviceTokens): PublishedCourse
    {
        for ($attempt = 0; $attempt <= $this->attempts; $attempt++) {
            if (($task['status'] ?? null) === 'completed') {
                return $this->created($task);
            }

            if ($attempt === $this->attempts) {
                break;
            }

            $getTaskRequest = new GetTaskRequest($taskId);
            $getTaskRequest->delay()->set($this->pauseMilliseconds);

            $response = $this->send($getTaskRequest, $serviceTokens);

            if (! $response->successful()) {
                throw $this->failure($response);
            }

            $next = $response->json('task');
            $task = is_array($next) ? $next : [];
        }

        return new PublishedCourse(self::PENDING.$taskId, processing: true);
    }

    /**
     * @param  array<array-key, mixed>  $task
     */
    private function created(array $task): PublishedCourse
    {
        $items = is_array($task['items'] ?? null) ? $task['items'] : [];

        foreach ($items as $item) {
            $id = is_array($item) ? ($item['item_id'] ?? null) : null;

            if (is_array($item) && ($item['item_type'] ?? null) === 'route' && (is_int($id) || is_string($id))) {
                return new PublishedCourse((string) $id, $this->rideWithGpsConnector->resolveBaseUrl().'/routes/'.$id);
            }
        }

        throw new ServiceUnavailable('Ride with GPS could not read the route.');
    }

    private function filename(string $title): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');

        return ($slug === '' ? 'traceway' : $slug).'.gpx';
    }

    private function send(Request $request, ServiceTokens $serviceTokens): Response
    {
        $request->authenticate(new TokenAuthenticator($serviceTokens->accessToken));

        try {
            $response = $this->rideWithGpsConnector->send($request);
        } catch (FatalRequestException $fatalRequestException) {
            throw new ServiceUnavailable('Ride with GPS could not be reached.', $fatalRequestException->getCode(), previous: $fatalRequestException);
        }

        throw_if($response->status() === 401, ServiceRefused::class, 'Ride with GPS no longer accepts Traceway. Connect it again.');

        return $response;
    }

    private function failure(Response $response): ServiceUnavailable
    {
        return new ServiceUnavailable('Ride with GPS did not take the route ('.$response->status().').');
    }
}
