<?php

declare(strict_types=1);

namespace App\Bus;

use App\Contracts\Bus\DispatchesCommands;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * One explicit map from a command to its handler. The controller never
 * chooses a handler, and a command cannot run without a line in this map.
 */
final readonly class CommandBus implements DispatchesCommands
{
    /**
     * @param  array<class-string, class-string>  $handlers
     */
    public function __construct(
        private Container $container,
        private array $handlers,
    ) {}

    public function dispatch(object $command): mixed
    {
        $class = $this->handlers[$command::class] ?? null;

        throw_if($class === null, RuntimeException::class, 'No handler for '.$command::class.'.');

        $handler = $this->container->make($class);

        throw_unless(is_object($handler), RuntimeException::class, $class.' could not be built.');

        $callback = [$handler, 'handle'];

        throw_unless(is_callable($callback), RuntimeException::class, $class.' cannot handle '.$command::class.'.');

        return $callback($command);
    }
}
