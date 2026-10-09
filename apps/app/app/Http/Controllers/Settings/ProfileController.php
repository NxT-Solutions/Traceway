<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Account\Email;
use App\Account\RiderId;
use App\Account\RiderName;
use App\Commands\DeleteAccountCommand;
use App\Commands\UpdateProfileCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use OpenApi\Attributes\Delete;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Patch;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response as OpenApiResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    #[Patch(
        path: '/settings/profile',
        operationId: 'updateProfile',
        summary: "Update the signed-in person's name and email",
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/ProfileUpdateRequest',
        description: 'The name and email of the signed-in person.',
        required: true,
    )]
    #[OpenApiResponse(response: HttpResponse::HTTP_FOUND, description: 'The profile was saved.')]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The name or email is not valid.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function update(ProfileUpdateRequest $profileUpdateRequest, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $user = $profileUpdateRequest->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new UpdateProfileCommand(
            riderId: new RiderId($user->id),
            name: new RiderName($profileUpdateRequest->string('name')->toString()),
            email: new Email($profileUpdateRequest->string('email')->toString()),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    #[Delete(
        path: '/settings/profile',
        operationId: 'deleteAccount',
        summary: 'Delete the signed-in person and their saved routes',
        security: [['session' => []]],
        tags: ['Account'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/ProfileDeleteRequest',
        description: 'Confirms the deletion. The password is not returned.',
        required: true,
    )]
    #[OpenApiResponse(response: HttpResponse::HTTP_CONFLICT, description: 'The account is gone and the browser is sent home.')]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[OpenApiResponse(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The password does not confirm the deletion.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function destroy(ProfileDeleteRequest $profileDeleteRequest, DispatchesCommands $dispatchesCommands): HttpResponse
    {
        $user = $profileDeleteRequest->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new DeleteAccountCommand(new RiderId($user->id)));

        Auth::logout();

        $profileDeleteRequest->session()->invalidate();
        $profileDeleteRequest->session()->regenerateToken();

        return Inertia::location(url('/'));
    }
}
