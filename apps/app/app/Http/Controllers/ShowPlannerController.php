<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\RiderId;
use App\Auth\Impersonation;
use App\Contracts\Account\UserRepository;
use App\Domain\Routing\Sport;
use App\Models\User;
use App\Queries\Status\ServiceStatusQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowPlannerController extends Controller
{
    /**
     * The planner shows a rider around on their first visit. An admin acting as the rider does not use up the
     * rider's tour.
     */
    public function __invoke(Request $request, ServiceStatusQuery $serviceStatusQuery, UserRepository $userRepository): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('dashboard', [
            'serviceNotice' => $serviceStatusQuery->banner(),
            'sportSurfaces' => Sport::defaultSurfaces(),
            'showTour' => ! Impersonation::active($request) && ! $userRepository->toured(new RiderId($user->id)),
        ]);
    }
}
