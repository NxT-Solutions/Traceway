<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\AdminActivityQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminActivityController extends Controller
{
    public function __invoke(Request $request, AdminActivityQuery $adminActivityQuery): Response
    {
        return Inertia::render('admin/activity', [
            'activity' => $adminActivityQuery->page($request->integer('page', 1)),
        ]);
    }
}
