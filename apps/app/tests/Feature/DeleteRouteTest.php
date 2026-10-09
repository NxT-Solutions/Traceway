<?php

declare(strict_types=1);

use App\Account\RiderId;
use App\Commands\DeleteRouteCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Models\SavedRoute;
use App\Models\User;
use App\Routes\RouteId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

test('a saved route cannot be deleted twice', function (): void {
    $user = User::factory()->create();
    $route = SavedRoute::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $dispatchesCommands = resolve(DispatchesCommands::class);
    $command = new DeleteRouteCommand(new RouteId($route->id), new RiderId($user->id));

    $dispatchesCommands->dispatch($command);

    expect(fn () => $dispatchesCommands->dispatch($command))->toThrow(NotFoundHttpException::class);
    expect(SavedRoute::query()->find($route->id))->toBeNull();
});

test('a missing route is not found', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('routes.saved.destroy', '00000000-0000-0000-0000-000000000000'))
        ->assertNotFound();
});
