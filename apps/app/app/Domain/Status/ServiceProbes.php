<?php

declare(strict_types=1);

namespace App\Domain\Status;

/**
 * Every check the status page runs. A service Traceway is not set up to use is left out.
 */
final readonly class ServiceProbes
{
    /**
     * @param  list<ServiceProbe>  $probes
     */
    public function __construct(private array $probes) {}

    /**
     * @return list<ServiceProbe>
     */
    public function all(): array
    {
        return $this->probes;
    }
}
