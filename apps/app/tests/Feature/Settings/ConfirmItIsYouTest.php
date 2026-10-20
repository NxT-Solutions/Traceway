<?php

declare(strict_types=1);

use App\Models\ConnectedAccount;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Support\DrawnPage;

uses(RefreshDatabase::class);

/**
 * A rider who opened the account with Google, connected Strava, and never set a password.
 */
function confirmItIsYouRider(): User
{
    foreach (['google', 'strava'] as $slug) {
        config(["services.{$slug}.client_id" => $slug.'-id', "services.{$slug}.client_secret" => $slug.'-secret']);
    }

    $user = User::factory()->create(['password' => null]);
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);
    ConnectedAccount::factory()->for($user)->signInOnly('strava')->create(['provider_user_id' => '2002']);

    return $user;
}

/**
 * The words of an element, leaving out what these selectors match.
 */
function confirmItIsYouWords(Element $element, string $leftOut): string
{
    $node = $element->cloneNode(deep: true);
    assert($node instanceof Element);
    $nodeList = $node->querySelectorAll($leftOut);

    foreach ($nodeList as $part) {
        $part->remove();
    }

    return (string) $node->textContent;
}

/**
 * What each service button says, to a screen reader and on screen. The marks are hidden from both.
 *
 * @return list<array{string, string}>
 */
function confirmItIsYouButtons(HTMLDocument $htmlDocument): array
{
    $buttons = [];
    $nodeList = $htmlDocument->querySelectorAll('.providers a');

    foreach ($nodeList as $link) {
        $buttons[] = [confirmItIsYouWords($link, '[aria-hidden="true"]'), confirmItIsYouWords($link, '[aria-hidden="true"], .vh')];
    }

    return $buttons;
}

it('says Continue with Google, as Google asks, and Confirm with the services that ask nothing', function (): void {
    $htmlDocument = DrawnPage::of($this->actingAs(confirmItIsYouRider())->get(route('password.confirm'))->assertOk());

    expect(confirmItIsYouButtons($htmlDocument))->toBe([
        ['Continue with Google, to confirm it is you', 'Continue with Google'],
        ['Confirm with Strava', 'Confirm with Strava'],
    ]);
});

it('tells a rider without a password in plain words why it asks', function (): void {
    $htmlDocument = DrawnPage::of($this->actingAs(confirmItIsYouRider())->get(route('password.confirm'))->assertOk());

    expect($htmlDocument->querySelector('h1')?->textContent)->toBe('Confirm it is you')
        ->and($htmlDocument->querySelector('.lede')?->textContent)->toBe('Before you go on, show it is you.')
        ->and($htmlDocument->querySelector('form'))->toBeNull();
});

it('asks a rider with a password for it, with no services', function (): void {
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);
    $user = User::factory()->create();
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);

    $htmlDocument = DrawnPage::of($this->actingAs($user)->get(route('password.confirm'))->assertOk());

    expect($htmlDocument->querySelectorAll('.providers a'))->toHaveCount(0)
        ->and($htmlDocument->querySelector('#password'))->not->toBeNull()
        ->and(substr_count((string) $htmlDocument->body?->textContent, 'or confirm with password'))->toBe(1);
});

it('reads why the service did not confirm it is the rider once, by focus and not as an alert too', function (): void {
    $user = confirmItIsYouRider();
    Socialite::fake('google', (new SocialiteUser)
        ->setRaw(['sub' => '9999', 'email' => 'ann@gmail.com', 'email_verified' => true])
        ->map(['id' => '9999', 'name' => 'Ann Rider', 'email' => 'ann@gmail.com'])
        ->setApprovedScopes(['openid', 'email', 'profile']));

    $this->actingAs($user)->get(route('password.confirm.connection', ['provider' => 'google']));
    $this->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']))
        ->assertRedirect(route('password.confirm'));

    $note = DrawnPage::of($this->get(route('password.confirm'))->assertOk())->querySelector('.notice.bad');

    expect($note?->textContent)->toBe('That Google account is not the one connected to your Traceway account.')
        ->and($note?->getAttribute('tabindex'))->toBe('-1')
        ->and($note?->hasAttribute('role'))->toBeFalse();
});
