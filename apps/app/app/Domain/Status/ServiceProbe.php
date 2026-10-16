<?php

declare(strict_types=1);

namespace App\Domain\Status;

/**
 * Asks one part of Traceway, or a service it uses, whether it works.
 */
interface ServiceProbe
{
    public function component(): StatusComponent;

    /**
     * Never throws: a service that cannot be reached is down.
     */
    public function check(): ProbeResult;
}
