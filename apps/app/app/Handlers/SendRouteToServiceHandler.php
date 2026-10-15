<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\RouteService;
use App\Account\RouteServiceTokens;
use App\Commands\SendRouteToServiceCommand;
use App\Contracts\Account\RouteServiceRepository;
use App\Contracts\Auth\CurrentRider;
use App\Domain\Courses\DeviceName;
use App\Domain\Courses\FitCourse;
use App\Domain\Courses\GpxCourse;
use App\Domain\Courses\Services\CourseServices;
use App\Domain\Courses\Services\CourseUpload;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use App\Domain\Routing\Sport;
use App\Queries\Routes\SharedCourse;
use App\Queries\Routes\SharedCourseQuery;
use App\Routes\SavedLeg;
use App\Routes\SentRoute;

final readonly class SendRouteToServiceHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private CourseServices $courseServices,
        private RouteServiceRepository $routeServiceRepository,
        private SharedCourseQuery $sharedCourseQuery,
    ) {}

    public function handle(SendRouteToServiceCommand $sendRouteToServiceCommand): SentRoute
    {
        $riderId = $sendRouteToServiceCommand->riderId;
        $service = $sendRouteToServiceCommand->routeService;

        $this->currentRider->assertSame($riderId);

        abort_unless($this->courseServices->has($service->value), 404);

        $stored = $this->routeServiceRepository->tokens($riderId, $service);

        abort_if(! $stored instanceof RouteServiceTokens, 409, 'Connect '.$service->label().' first.');

        $sharedCourse = $this->sharedCourseQuery->findOwned($sendRouteToServiceCommand->routeId, $riderId->value);
        $upload = $this->upload($sharedCourse, $sendRouteToServiceCommand);
        $courseService = $this->courseServices->for($service->value);
        $tokens = new ServiceTokens($stored->accessToken, $stored->refreshToken, $stored->expiresAt);

        try {
            if ($tokens->expiresBy(now()->getTimestamp())) {
                $tokens = $courseService->refresh($tokens);
                $this->routeServiceRepository->refreshed($riderId, $service, new RouteServiceTokens($tokens->accessToken, $tokens->refreshToken, $tokens->expiresAt));
            }

            $published = $courseService->publish(
                $tokens,
                $upload,
                $this->routeServiceRepository->sentId($riderId, $sendRouteToServiceCommand->routeId, $sendRouteToServiceCommand->leg, $service),
            );
        } catch (ServiceRefused) {
            $this->routeServiceRepository->needsReconnect($riderId, $service);

            abort(409, $service->label().' needs you to connect it again.');
        } catch (ServiceUnavailable) {
            abort(502, $this->unavailable($service));
        }

        $this->routeServiceRepository->recordSent(
            $riderId,
            $sendRouteToServiceCommand->routeId,
            $sendRouteToServiceCommand->leg,
            $service,
            $published->id,
            $published->url,
        );

        return new SentRoute($published->processing, $published->url);
    }

    /**
     * Both files of the route, or of one land leg. A multisport plan goes leg by leg, like its FIT courses.
     */
    private function upload(SharedCourse $sharedCourse, SendRouteToServiceCommand $sendRouteToServiceCommand): CourseUpload
    {
        $leg = $sendRouteToServiceCommand->leg;
        $base = trim($sharedCourse->name) === '' ? 'Traceway' : trim($sharedCourse->name);
        $externalId = $sendRouteToServiceCommand->routeId->value.($leg > 0 ? '-'.$leg : '');
        $updatedAt = $sharedCourse->updatedAt ?? now()->toIso8601String();

        if ($leg === 0) {
            abort_if($sharedCourse->legs !== [], 422, 'Send a multisport plan one leg at a time.');

            $deviceName = DeviceName::forCourse($base)->value;

            return $this->course(
                $base,
                $deviceName,
                $sharedCourse->sport,
                $sharedCourse->coordinates,
                $sharedCourse,
                $sharedCourse->distanceMetres,
                $sharedCourse->ascentMetres,
                $externalId,
                $updatedAt,
            );
        }

        $savedLeg = $sharedCourse->legs[$leg - 1] ?? null;

        abort_unless($savedLeg instanceof SavedLeg && $savedLeg->sport instanceof Sport, 404);

        $deviceName = DeviceName::forLeg($base, $leg, $savedLeg->sport)->value;

        return $this->course(
            $base.', leg '.$leg,
            $deviceName,
            $savedLeg->sport,
            $savedLeg->coordinates,
            $savedLeg,
            $savedLeg->distanceMetres,
            $savedLeg->ascentMetres,
            $externalId,
            $updatedAt,
        );
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    private function course(string $title, string $deviceName, Sport $sport, array $coordinates, SharedCourse|SavedLeg $source, int $distanceMetres, int $ascentMetres, string $externalId, string $updatedAt): CourseUpload
    {
        $start = $coordinates[0] ?? [0.0, 0.0];

        return new CourseUpload(
            $title,
            $deviceName,
            $sport,
            FitCourse::encode($deviceName, $sport, $coordinates, $source->details),
            GpxCourse::encode($title, $coordinates, $source->details),
            $distanceMetres,
            $ascentMetres,
            $start[1],
            $start[0],
            $externalId,
            $updatedAt,
        );
    }

    private function unavailable(RouteService $routeService): string
    {
        return $routeService->label().' did not take the route just now. Try again in a moment.';
    }
}
