<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * The content site owns /. A normal redirect makes Inertia follow that
     * document and open it in the error dialog on top of Settings.
     */
    public function toResponse($request): Response
    {
        return Inertia::location(url('/'));
    }
}
