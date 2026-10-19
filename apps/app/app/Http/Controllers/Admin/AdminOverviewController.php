<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\AdminOverviewQuery;
use Inertia\Inertia;
use Inertia\Response;

class AdminOverviewController extends Controller
{
    public function __invoke(AdminOverviewQuery $adminOverviewQuery): Response
    {
        return Inertia::render('admin/overview', [
            'overview' => $adminOverviewQuery->page(),
        ]);
    }
}
