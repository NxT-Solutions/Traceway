<?php

declare(strict_types=1);

namespace App\Account;

final readonly class Rider
{
    public function __construct(
        public RiderId $id,
        public RiderName $name,
        public Email $email,
    ) {}
}
