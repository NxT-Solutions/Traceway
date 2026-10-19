<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\TrafficInsightsQuery;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Traffic pages beyond visitors. Each asks Cloudflare after the page has drawn, so it never waits on it.
 */
class AdminTrafficInsightsController extends Controller
{
    public function requests(TrafficInsightsQuery $trafficInsightsQuery): Response
    {
        return Inertia::render('admin/traffic/requests', [
            'insights' => Inertia::defer(fn (): array => $trafficInsightsQuery->requests()),
        ]);
    }

    public function security(TrafficInsightsQuery $trafficInsightsQuery): Response
    {
        return Inertia::render('admin/traffic/security', [
            'insights' => Inertia::defer(fn (): array => $trafficInsightsQuery->security()),
        ]);
    }

    public function bots(TrafficInsightsQuery $trafficInsightsQuery): Response
    {
        return Inertia::render('admin/traffic/bots', [
            'insights' => Inertia::defer(fn (): array => $trafficInsightsQuery->bots()),
        ]);
    }

    public function speed(TrafficInsightsQuery $trafficInsightsQuery): Response
    {
        return Inertia::render('admin/traffic/speed', [
            'insights' => Inertia::defer(fn (): array => $trafficInsightsQuery->speed()),
        ]);
    }

    public function backups(TrafficInsightsQuery $trafficInsightsQuery): Response
    {
        return Inertia::render('admin/traffic/backups', [
            'insights' => Inertia::defer(fn (): array => $trafficInsightsQuery->backups()),
        ]);
    }
}
