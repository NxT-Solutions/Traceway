<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auth\Impersonation;
use App\Contracts\Usage\UsageCounter;
use App\Domain\Usage\UsageMetric;
use Illuminate\Auth\Events\Login;

/**
 * Adds a sign-in to today's total. The total does not say who signed in.
 */
final readonly class CountSignIn
{
    public function __construct(private UsageCounter $usageCounter) {}

    public function handle(Login $login): void
    {
        // A Super Admin starting to act as a rider is not the rider signing in.
        if ($login->guard !== 'web' || Impersonation::active(request())) {
            return;
        }

        $this->usageCounter->add(UsageMetric::SignIns->value, now()->toDateTimeImmutable());
    }
}
