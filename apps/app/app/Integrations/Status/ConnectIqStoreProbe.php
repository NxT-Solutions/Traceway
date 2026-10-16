<?php

declare(strict_types=1);

namespace App\Integrations\Status;

use App\Domain\Status\ProbeResult;
use App\Domain\Status\ServiceProbe;
use App\Domain\Status\ServiceState;
use App\Domain\Status\StatusComponent;
use App\Integrations\Status\Requests\PingRequest;
use Throwable;

/**
 * Whether riders can find the Garmin app in the Connect IQ store. The store's own app API
 * names the app once Garmin has approved it, and says "not approved" until then.
 */
final readonly class ConnectIqStoreProbe implements ServiceProbe
{
    public function __construct(
        private StatusConnector $statusConnector,
        private string $storeId,
    ) {}

    public function component(): StatusComponent
    {
        return StatusComponent::GarminStore;
    }

    public function check(): ProbeResult
    {
        $started = hrtime(as_number: true);

        try {
            $response = $this->statusConnector->send(new PingRequest('/api/appsLibraryExternalServices/api/asw/apps/'.rawurlencode($this->storeId)));
            $milliseconds = HttpServiceProbe::since($started);

            if ($response->successful() && $response->json('id') === $this->storeId) {
                return ProbeResult::answered($milliseconds);
            }

            if ($response->status() === 404 && str_contains(strtolower($response->body()), 'not approved')) {
                return new ProbeResult(ServiceState::Waiting, $milliseconds);
            }
        } catch (Throwable) {
            // The store did not answer, or answered with something that is not JSON.
        }

        return ProbeResult::down(HttpServiceProbe::since($started));
    }
}
