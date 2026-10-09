<?php

declare(strict_types=1);

namespace App\Contracts\Bus;

interface DispatchesCommands
{
    public function dispatch(object $command): mixed;
}
