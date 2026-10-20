<?php

declare(strict_types=1);

use App\Models\ConnectedAccount;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DrawnPage;

uses(RefreshDatabase::class);

/**
 * Gives Traceway keys for these services, which turns them on.
 */
function connectionsPageWith(string ...$slugs): void
{
    foreach ($slugs as $slug) {
        config(["services.{$slug}.client_id" => $slug.'-id', "services.{$slug}.client_secret" => $slug.'-secret']);
    }
}

it('lists what the rider connected, then what they could add', function (): void {
    connectionsPageWith('google', 'wahoo', 'github');
    $user = User::factory()->create(['password' => null]);
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['account_label' => 'ann@gmail.com']);
    ConnectedAccount::factory()->for($user)->signInOnly('garmin_connect')->create(['account_label' => 'Ann on Garmin']);

    $this->actingAs($user)
        ->get(route('connections.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('settings/connections')
            ->where('hasPassword', expected: false)
            ->where('hasPasskey', expected: false)
            ->has('connected', 2)
            ->where('connected.0.slug', 'google')
            ->where('connected.0.group', 'accounts')
            ->where('connected.0.available', expected: true)
            ->where('connected.0.connected', expected: true)
            ->where('connected.0.account_label', 'ann@gmail.com')
            ->where('connected.0.capabilities', [['key' => 'sign_in', 'label' => 'Log in', 'later' => false]])
            ->where('connected.0.manage_url', 'https://myaccount.google.com/connections')
            ->where('connected.0.withdraws_access', expected: false)
            ->where('connected.0.can_disconnect', expected: false)
            ->where('connected.0.disconnect_blocked', 'Add a password or a passkey first, so you can still log in.')
            ->where('connected.1.slug', 'garmin_connect')
            ->where('connected.1.available', expected: false)
            ->where('connected.1.account_label', expected: null)
            ->where('connected.1.capabilities', [
                ['key' => 'sign_in', 'label' => 'Log in', 'later' => false],
                ['key' => 'send_routes', 'label' => 'Send routes', 'later' => true],
            ])
            ->where('connected.1.can_disconnect', expected: true)
            ->where('connected.1.disconnect_blocked', expected: null)
            ->has('available', 2)
            ->where('available.0.slug', 'wahoo')
            ->where('available.0.connected', expected: false)
            ->where('available.0.capabilities', [
                ['key' => 'sign_in', 'label' => 'Log in', 'later' => false],
                ['key' => 'send_routes', 'label' => 'Send routes', 'later' => false],
                ['key' => 'sync_routes', 'label' => 'Keep routes in sync', 'later' => true],
            ])
            ->where('available.1.slug', 'github')
            ->missing('available.0.manage_url'));
});

it('lets a rider with a password disconnect anything, and never sends the keys to the page', function (): void {
    connectionsPageWith('wahoo');
    $user = User::factory()->create();
    ConnectedAccount::factory()->for($user)->create(['access_token' => 'wahoo-access-token', 'refresh_token' => 'wahoo-refresh-token']);

    $this->actingAs($user)
        ->get(route('connections.edit'))
        ->assertOk()
        ->assertDontSee('wahoo-access-token')
        ->assertDontSee('wahoo-refresh-token')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('hasPassword', expected: true)
            ->where('connected.0.slug', 'wahoo')
            ->where('connected.0.withdraws_access', expected: true)
            ->where('connected.0.manage_url', expected: null)
            ->where('connected.0.can_disconnect', expected: true)
            ->where('connected.0.disconnect_blocked', expected: null)
            ->missing('connected.0.access_token')
            ->where('available', []));
});

it('tells the page whether the rider confirmed their email, since Connect and Disconnect wait for it', function (): void {
    connectionsPageWith('google');

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('connections.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('emailVerified', expected: false)
            ->where('verificationSent', expected: false));

    $this->actingAs(User::factory()->create())
        ->get(route('connections.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('emailVerified', expected: true));
});

it('says the confirmation email is on its way once the rider asks for it again', function (): void {
    Notification::fake();
    connectionsPageWith('google');
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->from(route('connections.edit'))
        ->post(route('verification.send'))
        ->assertRedirect(route('connections.edit'));

    Notification::assertSentTo($user, VerifyEmail::class);

    $this->get(route('connections.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('verificationSent', expected: true));
});

it('turns Connect and Disconnect off until the email is confirmed, and says why once', function (): void {
    connectionsPageWith('google', 'github');
    $user = User::factory()->unverified()->create();
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create();

    $htmlDocument = DrawnPage::of($this->actingAs($user)->get(route('connections.edit'))->assertOk());
    $disconnect = $htmlDocument->querySelector('[data-disconnect="google"]');
    $connect = $htmlDocument->querySelector('.cx-app .cx-action');

    expect($htmlDocument->getElementById('connections-confirm-email')?->textContent)->toBe('Confirm your email first. Then you can add or remove a connection.')
        ->and($htmlDocument->querySelector('.cx-confirm button')?->textContent)->toBe('Send the email again')
        ->and($disconnect?->hasAttribute('disabled'))->toBeTrue()
        ->and($disconnect?->getAttribute('aria-describedby'))->toBe('connections-confirm-email')
        ->and($connect?->tagName)->toBe('BUTTON')
        ->and($connect?->textContent)->toBe('Connect GitHub')
        ->and($connect?->hasAttribute('disabled'))->toBeTrue()
        ->and($connect?->getAttribute('aria-describedby'))->toBe('connections-confirm-email');
});

it('offers Connect and Disconnect once the email is confirmed', function (): void {
    connectionsPageWith('google', 'github');
    $user = User::factory()->create();
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create();

    $htmlDocument = DrawnPage::of($this->actingAs($user)->get(route('connections.edit'))->assertOk());
    $disconnect = $htmlDocument->querySelector('[data-disconnect="google"]');
    $connect = $htmlDocument->querySelector('.cx-app .cx-action');

    expect($htmlDocument->getElementById('connections-confirm-email'))->toBeNull()
        ->and($disconnect?->hasAttribute('disabled'))->toBeFalse()
        ->and($disconnect?->hasAttribute('aria-describedby'))->toBeFalse()
        ->and($connect?->tagName)->toBe('A')
        ->and($connect?->getAttribute('href'))->toBe('/settings/connections/github/connect');
});
