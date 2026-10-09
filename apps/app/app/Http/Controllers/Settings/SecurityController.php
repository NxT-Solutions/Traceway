<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\RiderId;
use App\Commands\ChangePasswordCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use App\Queries\Account\SecurityQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Put;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response as OpenApiResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $twoFactorAuthenticationRequest, SecurityQuery $securityQuery): Response
    {
        $user = $twoFactorAuthenticationRequest->user();

        abort_unless($user instanceof User, 403);

        if (Features::canManageTwoFactorAuthentication()) {
            $twoFactorAuthenticationRequest->ensureStateIsValid();
        }

        return Inertia::render('settings/security', $securityQuery->page($user->id)->toArray());
    }

    /**
     * Update the user's password.
     */
    #[Put(
        path: '/settings/password',
        operationId: 'changePassword',
        summary: "Change the signed-in person's password",
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/PasswordUpdateRequest',
        description: 'The current password and the replacement. Neither is returned.',
        required: true,
    )]
    #[OpenApiResponse(response: HttpResponse::HTTP_FOUND, description: 'The password was changed. The new password is not returned.')]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The current password does not match, or the new password is too weak.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function update(PasswordUpdateRequest $passwordUpdateRequest, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $user = $passwordUpdateRequest->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new ChangePasswordCommand(
            riderId: new RiderId($user->id),
            password: $passwordUpdateRequest->string('password')->toString(),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
