<?php

declare(strict_types=1);

namespace App\Domain\Status;

/**
 * What one check found, and how long the answer took.
 */
final readonly class ProbeResult
{
    /** An answer slower than this is slow, even when it is right. */
    public const int SLOW_MILLISECONDS = 3000;

    public function __construct(
        public ServiceState $state,
        public int $milliseconds,
    ) {}

    /**
     * Up, or slow when the answer took too long.
     */
    public static function answered(int $milliseconds, ServiceState $serviceState = ServiceState::Up): self
    {
        return new self(
            $serviceState === ServiceState::Up && $milliseconds > self::SLOW_MILLISECONDS ? ServiceState::Slow : $serviceState,
            $milliseconds,
        );
    }

    public static function down(int $milliseconds): self
    {
        return new self(ServiceState::Down, $milliseconds);
    }
}
