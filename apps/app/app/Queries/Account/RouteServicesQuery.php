<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\RouteService;
use App\Domain\Courses\Services\CourseServices;
use App\Queries\RowReader;
use Illuminate\Support\Facades\DB;

/**
 * The services this Traceway can send to, and where the rider stands with each. Tokens are never selected.
 */
final readonly class RouteServicesQuery
{
    public function __construct(private CourseServices $courseServices) {}

    /**
     * @return list<array{service: string, label: string, connected: bool, needs_reconnect: bool, connected_at: string|null, last_sent_at: string|null}>
     */
    public function forRider(int $riderId): array
    {
        $rows = DB::table('route_service_accounts')
            ->where('user_id', $riderId)
            ->get(['service', 'needs_reconnect', 'connected_at', 'last_sent_at'])
            ->keyBy('service');

        $services = [];

        foreach (RouteService::cases() as $routeService) {
            if (! $this->courseServices->has($routeService->value)) {
                continue;
            }

            $row = $rows->get($routeService->value);
            $reader = is_object($row) ? new RowReader($row) : null;

            $services[] = [
                'service' => $routeService->value,
                'label' => $routeService->label(),
                'connected' => $reader instanceof RowReader,
                'needs_reconnect' => $reader instanceof RowReader && (bool) $reader->int('needs_reconnect'),
                'connected_at' => $reader?->iso('connected_at'),
                'last_sent_at' => $reader?->iso('last_sent_at'),
            ];
        }

        return $services;
    }
}
