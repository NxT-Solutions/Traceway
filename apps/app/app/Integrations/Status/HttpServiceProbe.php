<?php

declare(strict_types=1);

namespace App\Integrations\Status;

use App\Domain\Status\ProbeResult;
use App\Domain\Status\ServiceProbe;
use App\Domain\Status\StatusComponent;
use App\Integrations\Status\Requests\PingRequest;
use Throwable;

/**
 * A service works when one of its pages answers, and is slow when the answer takes long.
 */
final readonly class HttpServiceProbe implements ServiceProbe
{
    public function __construct(
        private StatusComponent $statusComponent,
        private StatusConnector $statusConnector,
        private string $path = '/',
    ) {}

    public function component(): StatusComponent
    {
        return $this->statusComponent;
    }

    public function check(): ProbeResult
    {
        $started = hrtime(as_number: true);

        try {
            $response = $this->statusConnector->send(new PingRequest($this->path));
        } catch (Throwable) {
            return ProbeResult::down(self::since($started));
        }

        return $response->successful()
            ? ProbeResult::answered(self::since($started))
            : ProbeResult::down(self::since($started));
    }

    public static function since(int|float $started): int
    {
        return (int) round((hrtime(as_number: true) - $started) / 1_000_000);
    }
}
