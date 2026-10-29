<?php

declare(strict_types=1);

namespace App\Integrations\Osrm;

use App\Domain\Routing\MapServerBusy;
use App\Domain\Routing\MapServerPace;
use Override;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Every request takes its share of the pace first. A server that turns
 * Traceway away as busy, or does not answer, gets a rest, and the caller
 * hears that it is busy.
 */
final class OsrmConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 2;

    protected int $requestTimeout = 12;

    /**
     * @param  bool  $serverPerProfile  The server keeps one OSRM per profile under its own path, as
     *                                  routing.openstreetmap.de does with /routed-bike and /routed-foot.
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?MapServerPace $mapServerPace = null,
        public readonly bool $serverPerProfile = false,
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    #[Override]
    public function send(Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        $this->mapServerPace?->admit(1);

        try {
            $response = parent::send($request, $mockClient, $handleRetry);
        } catch (FatalRequestException $fatalRequestException) {
            // Not reached, or no answer in time: busy, not a place without roads.
            $this->mapServerPace?->rest();

            throw new MapServerBusy('The backup map server did not answer.', $fatalRequestException->getCode(), previous: $fatalRequestException);
        } catch (RequestException $requestException) {
            if ($requestException->getStatus() === 429) {
                $this->mapServerPace?->rest();
            }

            throw $requestException;
        }

        if ($response->status() === 429) {
            $this->mapServerPace?->rest();
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'User-Agent' => 'Traceway/0.1 (route concept)',
        ];
    }
}
