<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\RiderId;
use App\Account\RouteService;
use App\Commands\ConnectRouteServiceCommand;
use App\Commands\DisconnectRouteServiceCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Domain\Courses\Services\CourseServices;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use OpenApi\Attributes\Delete;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response as OpenApiResponse;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Connects and disconnects the services a rider sends routes to, through each service's own sign-in.
 */
class RouteServicesController extends Controller
{
    /**
     * Sends the rider to the service to allow Traceway. The state guards the callback against a forged one.
     */
    public function connect(Request $request, string $service, CourseServices $courseServices): RedirectResponse
    {
        $routeService = $this->service($service, $courseServices);
        $state = Str::random(40);
        $return = $request->query('return');

        $request->session()->put('route_services.state.'.$routeService->value, $state);
        $request->session()->put('route_services.return', $this->safeReturn($return));

        return redirect()->away($courseServices->for($routeService->value)->authorizationUrl($state));
    }

    public function callback(Request $request, string $service, CourseServices $courseServices, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $routeService = $this->service($service, $courseServices);
        $expected = $request->session()->pull('route_services.state.'.$routeService->value);
        $return = $request->session()->pull('route_services.return');
        $target = is_string($return) && $return !== '' ? $return : route('devices.edit');
        $state = $request->query('state');
        $code = $request->query('code');

        abort_unless(is_string($expected) && is_string($state) && hash_equals($expected, $state), 403, 'This sign-in did not start here. Connect again from Settings.');

        if (! is_string($code) || $code === '') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __(':service was not connected.', ['service' => $routeService->label()])]);

            return redirect()->to($target);
        }

        try {
            $dispatchesCommands->dispatch(new ConnectRouteServiceCommand(
                new RiderId($this->user($request)->id),
                $routeService,
                $code,
            ));
        } catch (HttpExceptionInterface) {
            // The sign-in ran out, was used twice, or the service is down. The rider tries again from here.
            Inertia::flash('toast', ['type' => 'error', 'message' => __(':service could not be connected. Try again in a minute.', ['service' => $routeService->label()])]);

            return redirect()->to($target);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':service connected. Send a route to it from Send to device.', ['service' => $routeService->label()])]);

        return redirect()->to($target);
    }

    #[Delete(
        path: '/settings/services/{service}',
        operationId: 'disconnectRouteService',
        description: 'Traceway asks the service to withdraw its access, then forgets the tokens.',
        summary: 'Disconnect a service the rider sends routes to',
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[Parameter(
        name: 'service',
        description: 'The service.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', enum: ['wahoo', 'ride_with_gps']),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_FOUND,
        description: 'Back to Devices.',
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'This Traceway does not send to that service.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function destroy(Request $request, string $service, CourseServices $courseServices, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $routeService = $this->service($service, $courseServices);

        $dispatchesCommands->dispatch(new DisconnectRouteServiceCommand(
            new RiderId($this->user($request)->id),
            $routeService,
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':service disconnected.', ['service' => $routeService->label()])]);

        return to_route('devices.edit');
    }

    private function service(string $service, CourseServices $courseServices): RouteService
    {
        $routeService = RouteService::tryFrom($service);

        abort_unless($routeService instanceof RouteService && $courseServices->has($routeService->value), 404);

        return $routeService;
    }

    /**
     * Only a path on this site, so a crafted link cannot send the rider elsewhere after they connect.
     */
    private function safeReturn(mixed $return): ?string
    {
        if (! is_string($return) || ! str_starts_with($return, '/') || str_starts_with($return, '//') || str_contains($return, '\\')) {
            return null;
        }

        return $return;
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
