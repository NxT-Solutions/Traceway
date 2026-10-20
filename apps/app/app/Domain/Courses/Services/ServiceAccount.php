<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

/**
 * Who the rider is at a service, as the service says from the keys Traceway keeps: its number for the account,
 * and the email it shares, if any.
 */
final readonly class ServiceAccount
{
    public function __construct(
        public string $id,
        public ?string $email = null,
    ) {}
}
