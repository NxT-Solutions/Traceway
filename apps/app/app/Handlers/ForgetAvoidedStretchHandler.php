<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\ForgetAvoidedStretchCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Routes\AvoidedStretchRepository;
use App\Routes\ForeignAvoidedStretch;
use App\Routes\MissingAvoidedStretch;

final readonly class ForgetAvoidedStretchHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private AvoidedStretchRepository $avoidedStretchRepository,
    ) {}

    public function handle(ForgetAvoidedStretchCommand $forgetAvoidedStretchCommand): void
    {
        $this->currentRider->assertSame($forgetAvoidedStretchCommand->riderId);

        try {
            $this->avoidedStretchRepository->deleteOwned($forgetAvoidedStretchCommand->avoidedStretchId, $forgetAvoidedStretchCommand->riderId);
        } catch (MissingAvoidedStretch) {
            abort(404);
        } catch (ForeignAvoidedStretch) {
            abort(403);
        }
    }
}
