<?php

declare(strict_types=1);

use App\Mail\AccountMessage;
use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

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
    SavedRoute::factory()->create([
        'user_id' => $user->id,
        'name' => 'Morning loop',
    ]);

    $response = $this->actingAs($user)->get(route('privacy.export'));

    $response->assertOk();
    $response->assertDownload('traceway-data.json');
    expect($response->streamedContent())
        ->toContain('Morning loop')
        ->not->toContain($user->password);

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

test('a new account receives a welcome message', function (): void {
    Mail::fake();

    $this->post(route('register.store'), [
        'name' => 'New rider',
        'email' => 'new@traceway.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->hasTo('new@traceway.test') && $accountMessage->template === 'welcome');
});
