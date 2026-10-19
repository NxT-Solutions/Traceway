<?php

declare(strict_types=1);

use App\Mail\AccountMessage;
use App\Models\SavedRoute;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('a rider can see the data Traceway keeps', function (): void {
    $user = User::factory()->create(['name' => 'Road rider']);
    SavedRoute::factory()->create([
        'user_id' => $user->id,
        'name' => 'Morning loop',
    ]);

    $this->actingAs($user)
        ->get(route('privacy.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/privacy')
            ->where('account.name', 'Road rider')
            ->has('routes', 1)
            ->where('routes.0.name', 'Morning loop'));
});

test('a rider can download their data and receives a copy by email', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Road rider',
        'password' => 'password',
    ]);
    // The export itself is a visit, noted to the quarter hour.
    CarbonImmutable::setTestNow('2026-10-19 09:52:30');
    $user->assignRole('admin');
    SavedRoute::factory()->create([
        'user_id' => $user->id,
        'name' => 'Morning loop',
    ]);

    $response = $this->actingAs($user)->get(route('privacy.export'));

    $response->assertOk();
    $response->assertDownload('traceway-data.json');

    $document = json_decode((string) $response->streamedContent(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($response->streamedContent())
        ->toContain('Morning loop')
        ->not->toContain($user->password)
        ->and($document['account']['role'] ?? null)->toBe('admin')
        ->and($document['account']['last_seen_at'] ?? null)->toStartWith('2026-10-19T09:45:00');

    CarbonImmutable::setTestNow();

    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->hasTo($user->email)
        && $accountMessage->template === 'data-export'
        && $accountMessage->attachment !== null);
});

test('deleting an account sends a confirmation to that address', function (): void {
    Mail::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->hasTo($user->email) && $accountMessage->template === 'account-deleted');
});

test('a new account gets the confirmation link and no welcome yet', function (): void {
    Mail::fake();
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'New rider',
        'email' => 'new@traceway.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'new@traceway.test')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmail::class);
    Mail::assertNotQueued(AccountMessage::class);
});

test('a rider is welcomed once the email is confirmed', function (): void {
    Mail::fake();

    $user = User::factory()->unverified()->create(['email' => 'new@traceway.test']);

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]))
        ->assertRedirect();

    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->hasTo('new@traceway.test') && $accountMessage->template === 'welcome');
    Mail::assertQueuedCount(1);
});

test('confirming a changed email does not send the welcome again', function (): void {
    Mail::fake();

    $user = User::factory()->unverified()->create(['welcomed_at' => now()->subDay()]);

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]))
        ->assertRedirect();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    Mail::assertNotQueued(AccountMessage::class);
});
