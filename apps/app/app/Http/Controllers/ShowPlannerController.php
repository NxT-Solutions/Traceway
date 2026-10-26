<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\RiderId;
use App\Auth\Impersonation;
use App\Contracts\Account\UserRepository;
use App\Domain\Routing\Sport;
use App\Models\User;
use App\Queries\Routes\AvoidedStretchesQuery;
use App\Queries\Status\ServiceStatusQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShowPlannerController extends Controller
{
    /**
     * The planner shows a rider around on their first visit. An admin acting as the rider does not use up the
     * rider's tour. The roads the rider avoids are drawn on the map. A town typed on the home page arrives as
     * `near`, which the planner looks up once and shows.
     */
    public function __invoke(Request $request, ServiceStatusQuery $serviceStatusQuery, UserRepository $userRepository, AvoidedStretchesQuery $avoidedStretchesQuery): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('dashboard', [
            'serviceNotice' => $serviceStatusQuery->banner(),
            'sportSurfaces' => Sport::defaultSurfaces(),
            'showTour' => ! Impersonation::active($request) && ! $userRepository->toured(new RiderId($user->id)),
            'avoided' => $avoidedStretchesQuery->forRider($user->id),
            'near' => $this->near($request),
        ]);
    }

    /**
     * What the rider typed, as long as the planner's own search takes it. It survives signing up and checking the
     * email, because Laravel sends the rider back to the link they first asked for.
     */
    private function near(Request $request): ?string
    {
        $near = $request->query('near');

        if (! is_string($near) || ! mb_check_encoding($near, 'UTF-8')) {
            return null;
        }

        $near = Str::squish($near);
        $length = mb_strlen($near);

        return $length >= 2 && $length <= 120 ? $near : null;
    }
}
