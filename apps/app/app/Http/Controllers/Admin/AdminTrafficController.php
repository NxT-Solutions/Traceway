<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\TrafficQuery;
use Inertia\Inertia;
use Inertia\Response;

class AdminTrafficController extends Controller
{
    public function __invoke(TrafficQuery $trafficQuery): Response
    {
        return Inertia::render('admin/traffic', [
            'traffic' => Inertia::defer(fn (): array => $trafficQuery->page()),
        ]);
    }
}
