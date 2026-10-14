<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\RiderId;
use App\Contracts\Routes\RouteImageStore;
use App\Routes\RouteId;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Keeps each rider's route pictures in a folder of their own, so a deleted
 * account takes all of them with it.
 */
final readonly class DiskRouteImageStore implements RouteImageStore
{
    public function __construct(private Filesystem $filesystem) {}

    public function put(RiderId $riderId, RouteId $routeId, string $jpeg): void
    {
        $this->filesystem->put($this->path($riderId, $routeId), $jpeg);
    }

    public function get(RiderId $riderId, RouteId $routeId): ?string
    {
        return $this->filesystem->get($this->path($riderId, $routeId));
    }

    public function drawnAt(RiderId $riderId, RouteId $routeId): ?int
    {
        $path = $this->path($riderId, $routeId);

        return $this->filesystem->exists($path) ? $this->filesystem->lastModified($path) : null;
    }

    public function forget(RiderId $riderId, RouteId $routeId): void
    {
        $this->filesystem->delete($this->path($riderId, $routeId));
    }

    public function forgetRider(RiderId $riderId): void
    {
        $this->filesystem->deleteDirectory($this->folder($riderId));
    }

    private function folder(RiderId $riderId): string
    {
        return 'route-images/'.$riderId->value;
    }

    private function path(RiderId $riderId, RouteId $routeId): string
    {
        return $this->folder($riderId).'/'.$routeId->value.'.jpg';
    }
}
