<?php

declare(strict_types=1);

namespace App\Integrations\Wahoo;

use App\Domain\Courses\Services\CourseService;
use App\Domain\Courses\Services\CourseUpload;
use App\Domain\Courses\Services\PublishedCourse;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use App\Domain\Routing\Sport;
use App\Integrations\Wahoo\Requests\CreateRouteRequest;
use App\Integrations\Wahoo\Requests\FindRoutesRequest;
use App\Integrations\Wahoo\Requests\RevokePermissionsRequest;
use App\Integrations\Wahoo\Requests\UpdateRouteRequest;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Sends a course to the rider's Wahoo library as a FIT file. Wahoo syncs it to the ELEMNT on its own.
 */
final readonly class WahooCourseService implements CourseService
{
    public function __construct(private WahooConnector $wahooConnector) {}

    public function authorizationUrl(string $state): string
    {
        return $this->wahooConnector->getAuthorizationUrl(state: $state);
    }

    public function connect(string $code): ServiceTokens
    {
        try {
            $response = $this->wahooConnector->tradeCode($code);
        } catch (FatalRequestException $fatalRequestException) {
            throw new ServiceUnavailable('Wahoo could not be reached.', $fatalRequestException->getCode(), previous: $fatalRequestException);
        }

        throw_if($response->failed(), ServiceRefused::class, 'Wahoo did not accept the sign-in.');

        return $this->tokens($response);
    }

    public function refresh(ServiceTokens $serviceTokens): ServiceTokens
    {
        throw_if($serviceTokens->refreshToken === null, ServiceRefused::class, 'Wahoo gave no refresh token.');

        try {
            $response = $this->wahooConnector->tradeRefreshToken($serviceTokens->refreshToken);
        } catch (FatalRequestException $fatalRequestException) {
            throw new ServiceUnavailable('Wahoo could not be reached.', $fatalRequestException->getCode(), previous: $fatalRequestException);
        }

        throw_if($response->failed(), ServiceRefused::class, 'Wahoo did not refresh the sign-in.');

        return $this->tokens($response, $serviceTokens->refreshToken);
    }

    public function publish(ServiceTokens $serviceTokens, CourseUpload $courseUpload, ?string $previousId): PublishedCourse
    {
        $route = $this->route($courseUpload);

        if ($previousId !== null) {
            $response = $this->send(new UpdateRouteRequest($previousId, $route), $serviceTokens);

            if ($response->successful()) {
                return new PublishedCourse($previousId);
            }

            // The rider deleted it in Wahoo. Fall through and add it again.
            if ($response->status() !== 404) {
                throw $this->failure($response);
            }
        }

        $response = $this->send(new CreateRouteRequest(['external_id' => $courseUpload->externalId, ...$route]), $serviceTokens);

        if ($response->successful()) {
            return new PublishedCourse($this->id($response->json()));
        }

        // Wahoo keeps one route per external id. An earlier copy Traceway lost track of is updated instead.
        if ($response->status() === 422 || $response->status() === 409) {
            $existing = $this->existing($courseUpload->externalId, $serviceTokens);

            if ($existing !== null) {
                $updated = $this->send(new UpdateRouteRequest($existing, $route), $serviceTokens);

                if ($updated->successful()) {
                    return new PublishedCourse($existing);
                }

                throw $this->failure($updated);
            }
        }

        throw $this->failure($response);
    }

    public function revoke(ServiceTokens $serviceTokens): void
    {
        try {
            $this->send(new RevokePermissionsRequest, $serviceTokens);
        } catch (ServiceUnavailable) {
            // The rider is disconnecting either way.
        }
    }

    /**
     * @return array<string, string|int|float>
     */
    private function route(CourseUpload $courseUpload): array
    {
        return [
            'file' => 'data:application/vnd.fit;base64,'.base64_encode($courseUpload->fit),
            'filename' => $this->filename($courseUpload->title),
            'provider_updated_at' => $courseUpload->updatedAt,
            'name' => $courseUpload->title,
            'description' => 'Planned on Traceway',
            'workout_type_family_id' => $this->family($courseUpload->sport),
            'start_lat' => $courseUpload->startLatitude,
            'start_lng' => $courseUpload->startLongitude,
            'distance' => $courseUpload->distanceMetres,
            'ascent' => $courseUpload->ascentMetres,
        ];
    }

    /**
     * Wahoo's workout families: 0 biking, 1 running, 9 walking.
     */
    private function family(Sport $sport): int
    {
        return match ($sport) {
            Sport::Road, Sport::Gravel, Sport::Mtb => 0,
            Sport::Run, Sport::TrailRun => 1,
            Sport::Walk, Sport::Hike => 9,
        };
    }

    private function filename(string $title): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');

        return ($slug === '' ? 'traceway' : $slug).'.fit';
    }

    private function existing(string $externalId, ServiceTokens $serviceTokens): ?string
    {
        $response = $this->send(new FindRoutesRequest($externalId), $serviceTokens);

        if (! $response->successful()) {
            return null;
        }

        // Wahoo answers with a bare list or with {routes: [...]}.
        $body = $response->json();
        $routes = isset($body['routes']) && is_array($body['routes']) ? $body['routes'] : $body;

        foreach ($routes as $route) {
            if (is_array($route) && ($route['external_id'] ?? null) === $externalId) {
                return $this->id($route);
            }
        }

        return null;
    }

    private function id(mixed $route): string
    {
        $id = is_array($route) ? ($route['id'] ?? null) : null;

        throw_if(! is_int($id) && ! is_string($id), ServiceUnavailable::class, 'Wahoo did not say which route it stored.');

        return (string) $id;
    }

    private function send(Request $request, ServiceTokens $serviceTokens): Response
    {
        $request->authenticate(new TokenAuthenticator($serviceTokens->accessToken));

        try {
            $response = $this->wahooConnector->send($request);
        } catch (FatalRequestException $fatalRequestException) {
            throw new ServiceUnavailable('Wahoo could not be reached.', $fatalRequestException->getCode(), previous: $fatalRequestException);
        }

        throw_if($response->status() === 401, ServiceRefused::class, 'Wahoo no longer accepts Traceway. Connect it again.');

        return $response;
    }

    private function failure(Response $response): ServiceUnavailable
    {
        return new ServiceUnavailable('Wahoo did not take the route ('.$response->status().').');
    }

    private function tokens(Response $response, ?string $refreshToken = null): ServiceTokens
    {
        throw_unless(is_string($response->json('access_token')), ServiceUnavailable::class, 'Wahoo gave no token.');

        $oauthAuthenticator = $this->wahooConnector->authenticator($response, $refreshToken);

        return new ServiceTokens(
            $oauthAuthenticator->getAccessToken(),
            $oauthAuthenticator->getRefreshToken(),
            $oauthAuthenticator->getExpiresAt()?->getTimestamp(),
        );
    }
}
