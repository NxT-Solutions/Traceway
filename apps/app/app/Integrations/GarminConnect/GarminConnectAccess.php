<?php

declare(strict_types=1);

namespace App\Integrations\GarminConnect;

use App\Domain\Connections\GrantedAccess;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use App\Integrations\GarminConnect\Requests\DeleteRegistrationRequest;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Auth\TokenAuthenticator;

/**
 * The access Garmin Connect gave Traceway. Its keys last a day and refresh for about three months.
 */
final readonly class GarminConnectAccess implements GrantedAccess
{
    public function __construct(
        private GarminConnectTokenConnector $garminConnectTokenConnector,
        private GarminConnectConnector $garminConnectConnector,
    ) {}

    public function refresh(ServiceTokens $serviceTokens): ServiceTokens
    {
        throw_if($serviceTokens->refreshToken === null, ServiceRefused::class, 'Garmin gave no refresh token.');

        try {
            $response = $this->garminConnectTokenConnector->tradeRefreshToken($serviceTokens->refreshToken);
        } catch (FatalRequestException $fatalRequestException) {
            throw new ServiceUnavailable('Garmin could not be reached.', $fatalRequestException->getCode(), previous: $fatalRequestException);
        }

        // A server error or a busy answer says nothing about the keys, so the rider is not asked to connect again.
        throw_if($response->serverError() || in_array($response->status(), [408, 429], strict: true), ServiceUnavailable::class, 'Garmin could not refresh the sign-in now ('.$response->status().').');
        throw_if($response->failed(), ServiceRefused::class, 'Garmin did not refresh the sign-in.');
        throw_unless(is_string($response->json('access_token')), ServiceUnavailable::class, 'Garmin gave no token.');

        // Garmin hands out a new refresh token every time; the old one stops working.
        $oauthAuthenticator = $this->garminConnectTokenConnector->authenticator($response, $serviceTokens->refreshToken);

        return new ServiceTokens(
            $oauthAuthenticator->getAccessToken(),
            $oauthAuthenticator->getRefreshToken(),
            $oauthAuthenticator->getExpiresAt()?->getTimestamp(),
        );
    }

    public function revoke(ServiceTokens $serviceTokens): void
    {
        $deleteRegistrationRequest = new DeleteRegistrationRequest;
        $deleteRegistrationRequest->authenticate(new TokenAuthenticator($serviceTokens->accessToken));

        try {
            $response = $this->garminConnectConnector->send($deleteRegistrationRequest);
        } catch (FatalRequestException $fatalRequestException) {
            throw new ServiceUnavailable('Garmin could not be reached.', $fatalRequestException->getCode(), previous: $fatalRequestException);
        }

        throw_if($response->status() === 401, ServiceRefused::class, 'Garmin no longer accepts Traceway.');
        // A registration Garmin no longer has is ended already. Any other failure may have left it, so the caller
        // reports it, and lets the rider go either way.
        throw_if($response->failed() && $response->status() !== 404, ServiceUnavailable::class, 'Garmin did not end the registration ('.$response->status().').');
    }

    /**
     * Does nothing. Garmin's only way to withdraw ends the rider's whole registration, so it would stop the new
     * keys too. The old access key runs out within a day.
     */
    public function supersede(ServiceTokens $serviceTokens): void {}
}
