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
        $user = $request->user();
        $confirmedAt = $request->session()->get('auth.password_confirmed_at', 0);
        $timeout = config('auth.password_timeout', 10800);

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            // A rider who signed up with a connected account confirms it is them another way before deleting.
            'hasPassword' => ! $user instanceof User || $user->password !== null,
            'confirmedRecently' => is_numeric($confirmedAt) && is_numeric($timeout) && (int) $confirmedAt >= now()->getTimestamp() - (int) $timeout,
            // A new email the rider asked for, waiting for them to open the link sent to it.
            'pendingEmail' => $user instanceof User ? $user->pending_email : null,
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

        $email = new Email($profileUpdateRequest->string('email')->toString());

        $dispatchesCommands->dispatch(new UpdateProfileCommand(
            riderId: new RiderId($user->id),
            name: new RiderName($profileUpdateRequest->string('name')->toString()),
            email: $email,
        ));

        // A password typed here proves it is the rider, the same as the confirm page does.
        if ($profileUpdateRequest->filled('current_password')) {
            $profileUpdateRequest->session()->put('auth.password_confirmed_at', now()->getTimestamp());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => mb_strtolower($user->email) === $email->value
            ? __('Profile updated.')
            : __('Check :email. Your account signs in with it once you open the link sent there.', ['email' => $email->value])]);

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
