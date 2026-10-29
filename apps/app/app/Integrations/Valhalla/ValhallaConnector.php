<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla;

use App\Domain\Routing\MapServerPace;
use Override;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Pool;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Every request, and every request asked again, takes its share of the pace
 * first. When the server turns Traceway away as busy, or answers none of a
 * batch, Traceway rests. One slow or failed request on its own is not the
 * server down for everyone, so it is no reason to rest.
 */
final class ValhallaConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 2;

    protected int $requestTimeout = 12;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?MapServerPace $mapServerPace = null,
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

    #[Override]
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if ($exception instanceof RequestException && $exception->getStatus() === 429) {
            $this->mapServerPace?->rest();

            return false;
        }

        $this->mapServerPace?->admit(1);

        return true;
    }

    #[Override]
    public function pool(iterable|callable $requests = [], int|callable $concurrency = 5, ?callable $responseHandler = null, ?callable $exceptionHandler = null): Pool
    {
        $mapServerPace = $this->mapServerPace;
        $left = $this->size($requests);

        if (! $mapServerPace instanceof MapServerPace || $left === null) {
            return parent::pool($requests, $concurrency, $responseHandler, $exceptionHandler);
        }

        $mapServerPace->admit($left);

        $size = $left;
        $anyAnswered = false;
        $settle = static function (?Response $response) use ($mapServerPace, $size, &$left, &$anyAnswered): void {
            $anyAnswered = $anyAnswered || ($response instanceof Response && self::answered($response));
            $left--;

            // Turned away as busy is a rest at once. A batch of several with
            // no answer at all is too; one request on its own is not.
            if ($response?->status() === 429 || ($left === 0 && ! $anyAnswered && $size > 1)) {
                $mapServerPace->rest();
            }
        };

        return parent::pool(
            requests: $requests,
            concurrency: $concurrency,
            responseHandler: static function (Response $response, int|string $key, object $poolAggregate) use ($settle, $responseHandler): void {
                $settle($response);

                if ($responseHandler !== null) {
                    $responseHandler($response, $key, $poolAggregate);
                }
            },
            exceptionHandler: static function (mixed $reason, int|string $key, object $poolAggregate) use ($settle, $exceptionHandler): void {
                $settle($reason instanceof RequestException ? $reason->getResponse() : null);

                if ($exceptionHandler !== null) {
                    $exceptionHandler($reason, $key, $poolAggregate);
                }
            },
        );
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

    /**
     * How many requests a batch holds, or null when it is made as it goes.
     */
    private function size(mixed $requests): ?int
    {
        return is_countable($requests) ? count($requests) : null;
    }

    /**
     * An answer, even a no, and not a busy server or one that broke.
     */
    private static function answered(Response $response): bool
    {
        return $response->status() < 500 && $response->status() !== 429;
    }
}
