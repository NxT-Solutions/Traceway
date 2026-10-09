<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\Routes\LibraryQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedRouteLibraryController extends Controller
{
    public function __invoke(Request $request, LibraryQuery $libraryQuery): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('saved', $libraryQuery->page($user->id)->toArray());
    }
}
