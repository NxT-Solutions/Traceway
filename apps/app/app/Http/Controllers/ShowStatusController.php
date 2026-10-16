<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Status\ServiceStatusQuery;
use Inertia\Inertia;
use Inertia\Response;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\Response as OpenApiResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ShowStatusController extends Controller
{
    #[Get(
        path: '/status',
        operationId: 'serviceStatus',
        description: 'Public. Whether the planner, the map services, the apps a rider sends to, and the Garmin store listing work, with the last 90 days.',
        summary: 'See what works right now',
        security: [],
        tags: ['Status'],
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_OK,
        description: 'The status page.',
    )]
    public function __invoke(ServiceStatusQuery $serviceStatusQuery): Response
    {
        return Inertia::render('status', $serviceStatusQuery->page());
    }
}
