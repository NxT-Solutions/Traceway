<?php

declare(strict_types=1);

namespace App\Account;

use RuntimeException;
use Throwable;

/**
 * Connecting an account, or logging in with one, stopped. The message is the one the rider sees.
 */
final class ConnectionFailed extends RuntimeException
{
    public function __construct(
        public readonly ConnectionProvider $provider,
        public readonly ConnectionProblem $problem,
        ?Throwable $previous = null,
    ) {
        parent::__construct($problem->message($provider), 0, $previous);
    }

    public static function because(ConnectionProvider $connectionProvider, ConnectionProblem $connectionProblem, ?Throwable $throwable = null): self
    {
        return new self($connectionProvider, $connectionProblem, $throwable);
    }
}
