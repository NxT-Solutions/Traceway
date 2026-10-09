<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\Sport;
use App\Integrations\Valhalla\Requests\LocateRequest;
use App\Integrations\Valhalla\ValhallaConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class SnapPointController extends Controller
{
    public function __invoke(Request $request, ValhallaConnector $valhallaConnector): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'sport' => ['required', Rule::enum(Sport::class)],
        ])->validate();

        $sportValue = $validated['sport'] ?? null;
        $latitude = $validated['latitude'] ?? null;
        $longitude = $validated['longitude'] ?? null;

        abort_if(! is_string($sportValue) || ! is_numeric($latitude) || ! is_numeric($longitude), 422);

        $sport = Sport::from($sportValue);
        $onFoot = match ($sport) {
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => true,
            Sport::Road, Sport::Gravel, Sport::Mtb => false,
        };

        try {
            $response = $valhallaConnector->send(new LocateRequest(
                latitude: (float) $latitude,
                longitude: (float) $longitude,
                costing: $onFoot ? 'pedestrian' : 'bicycle',
            ));
        } catch (Throwable) {
            return $this->miss();
        }

        $latitude = $response->json('0.edges.0.correlated_lat');
        $longitude = $response->json('0.edges.0.correlated_lon');

        if (! $response->successful() || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return $this->miss();
        }

        return response()->json([
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ]);
    }

    private function miss(): JsonResponse
    {
        return response()->json([
            'message' => 'No road or path is close enough to put a point there.',
        ], 422);
    }
}
