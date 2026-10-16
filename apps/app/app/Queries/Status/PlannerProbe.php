<?php

declare(strict_types=1);

namespace App\Queries\Status;

use App\Domain\Status\ProbeResult;
use App\Domain\Status\ServiceProbe;
use App\Domain\Status\ServiceState;
use App\Domain\Status\StatusComponent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Traceway itself works when its database answers and the queue keeps up. A message
 * waiting more than ten minutes, such as a sign-up email, means the queue has stalled.
 */
final readonly class PlannerProbe implements ServiceProbe
{
    public const int QUEUE_LATE_SECONDS = 600;

    public function component(): StatusComponent
    {
        return StatusComponent::Planner;
    }

    public function check(): ProbeResult
    {
        $started = hrtime(as_number: true);

        try {
            DB::select('select 1');
            $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
        } catch (Throwable) {
            return ProbeResult::down($this->since($started));
        }

        $late = is_numeric($oldest) && (int) $oldest < now()->getTimestamp() - self::QUEUE_LATE_SECONDS;

        return ProbeResult::answered($this->since($started), $late ? ServiceState::Slow : ServiceState::Up);
    }

    private function since(int|float $started): int
    {
        return (int) round((hrtime(as_number: true) - $started) / 1_000_000);
    }
}
