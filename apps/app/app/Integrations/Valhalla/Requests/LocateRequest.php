<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Saloon\Traits\Plugins\HasTimeout;

final class LocateRequest extends Request implements HasBody
{
    use HasJsonBody;
    use HasTimeout;

    #[Override]
    protected Method $method = Method::POST;

    protected int $connectTimeout = 2;

    /**
     * The public router answers a busy caller late rather than not at all:
     * a lookup queued behind a long route takes seconds, not milliseconds.
     */
    protected int $requestTimeout = 8;

    /**
     * A router that turns the lookup away gets one more go, a second later.
     */
    #[Override]
    public ?int $tries = 2;

    #[Override]
    public ?int $retryInterval = 1000;

    /**
     * A router that kept saying no is busy, not broken: its last answer
     * comes back instead of an exception.
     */
    #[Override]
    public ?bool $throwOnMaxTries = false;

    /**
     * @param  array<string, array<string, float|int|string>>  $costingOptions  The same options the route is planned with, so a point lands only on a way the route may use.
     */
    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly string $costing,
        private readonly array $costingOptions,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/locate';
    }

    /**
     * Only a refusal or an error from the router. A timeout has already
     * waited its whole time, and a refused connection stays refused for a
     * while.
     */
    #[Override]
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->getStatus();

        return $status === 429 || $status >= 500;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'verbose' => true,
            'costing' => $this->costing,
            'costing_options' => $this->costingOptions,
            'locations' => [[
                'lat' => $this->latitude,
                'lon' => $this->longitude,
                'radius' => 80,
            ]],
        ];
    }
}
