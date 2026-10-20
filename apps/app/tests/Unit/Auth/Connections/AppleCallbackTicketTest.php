<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Sends a guest to Apple, has Apple post its answer, and hands back where the browser goes next. Apple's sign-in
 * is set up, and remembers whether Traceway asked it who the rider is.
 */
function appleCallbackTicketPosted(bool &$asked): string
{
    config([
        'services.apple.client_id' => 'online.traceway.signin',
        'services.apple.team_id' => 'TEAM123456',
        'services.apple.key_id' => 'KEY1234567',
        'services.apple.private_key' => 'a-private-key',
    ]);

    Socialite::fake('apple', function () use (&$asked): SocialiteUser {
        $asked = true;

        return (new SocialiteUser)->map(['id' => '001234.apple', 'name' => null, 'email' => null]);
    });

    test()->get(route('connections.sign-in', ['provider' => 'apple']));

    return (string) test()->post(route('connections.apple'), ['code' => 'apple-code', 'state' => 'apple-state'])->headers->get('Location');
}

it('turns away an Apple ticket two minutes after Apple posted it', function (): void {
    $asked = false;
    $location = appleCallbackTicketPosted($asked);

    $this->travel(121)->seconds();

    $this->get($location)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That took too long, or it started in another tab. Try again.']);

    $this->assertGuest();
    expect($asked)->toBeFalse();
});

it('reads an Apple ticket that is still within its two minutes', function (): void {
    $asked = false;
    $location = appleCallbackTicketPosted($asked);

    $this->travel(115)->seconds();

    // Apple gave no email, so the new rider goes on to finish opening their account.
    $this->get($location)->assertRedirect(route('connections.finish'));

    expect($asked)->toBeTrue();
});
