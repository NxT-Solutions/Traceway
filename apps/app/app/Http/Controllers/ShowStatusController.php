<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Status\StatusRange;
use App\Queries\Status\ServiceStatusQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response as OpenApiResponse;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ShowStatusController extends Controller
{
    #[Get(
        path: '/status',
        operationId: 'serviceStatus',
        description: 'Public. Whether the planner, the map services, the apps a rider sends to, and the Garmin store listing work, with a bar for each hour, day, or week of the range asked for.',
        summary: 'See what works right now',
        security: [],
        tags: ['Status'],
    )]
    #[Parameter(
        name: 'range',
        description: 'How far back the bars go: the last 24 hours by the hour (the default), 7, 30, or 90 days by the day, or a year by the week.',
        in: 'query',
        required: false,
        schema: new Schema(type: 'string', default: 'day', enum: ['day', 'week', 'month', 'quarter', 'year']),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_OK,
        description: 'The status page.',
    )]
    public function __invoke(Request $request, ServiceStatusQuery $serviceStatusQuery): Response
    {
        // An unknown range shows the default rather than an error: the address is shared by hand.
        $statusRange = StatusRange::tryFrom($request->string('range')->toString()) ?? StatusRange::Day;

        return Inertia::render('status', $serviceStatusQuery->page($statusRange));
    }
}
