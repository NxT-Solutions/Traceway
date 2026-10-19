<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Usage\UsageCounter;
use App\Domain\Usage\UsageMetric;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Adds one to today's total for a part of Traceway when a request to it works. The total says nothing about who
 * asked. Counting never stops the request.
 */
final readonly class CountUsage
{
    public function __construct(private UsageCounter $usageCounter) {}

    public static function as(UsageMetric $usageMetric): string
    {
        return self::class.':'.$usageMetric->value;
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $metric): Response
    {
        $response = $next($request);

        if ($response->isSuccessful() && UsageMetric::tryFrom($metric) instanceof UsageMetric) {
            try {
                $this->usageCounter->add($metric, now()->toDateTimeImmutable());
            } catch (Throwable $throwable) {
                report($throwable);
            }
        }

        return $response;
    }
}
