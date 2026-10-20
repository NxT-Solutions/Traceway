<?php

declare(strict_types=1);

use App\Mail\AccountMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Request::setTrustedHosts([]);
});

/**
 * An OpenStreetMap account, which has no email, comes back, and its owner lands on the last step of signing up.
 */
function signUpHostOpenStreetMapReturns(): void
{
    config(['services.openstreetmap.client_id' => 'osm-id', 'services.openstreetmap.client_secret' => 'osm-secret']);

    Socialite::fake('openstreetmap', (new SocialiteUser)
        ->map(['id' => '777', 'name' => 'Mallory', 'email' => null])
        ->setApprovedScopes(['read_prefs']));

    test()->get(route('connections.sign-in', ['provider' => 'openstreetmap']));
    test()->get(route('connections.callback', ['provider' => 'openstreetmap', 'code' => 'from-openstreetmap', 'state' => 'state']))
        ->assertRedirect(route('connections.finish'));
}

it("puts the emailed link on Traceway's own address, whatever host the request names", function (string $url, array $headers): void {
    Mail::fake();
    config(['app.url' => 'https://traceway.test']);
    signUpHostOpenStreetMapReturns();

    $this->withHeaders($headers)
        ->post($url, ['name' => 'Boss', 'email' => 'boss@example.com'])
        ->assertSessionDoesntHaveErrors();

    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-sign-up'
        && str_starts_with($accountMessage->values['url'], 'https://traceway.test/login/finish/')
        && $accountMessage->values['home'] === 'https://traceway.test');
})->with([
    'a forwarded host' => ['/login/finish', ['X-Forwarded-Host' => 'evil.test']],
    'another host' => ['http://evil.test/login/finish', []],
]);

it('takes no host from a forwarded header', function (): void {
    $location = $this->withHeaders(['X-Forwarded-Host' => 'evil.test', 'X-Forwarded-Proto' => 'https'])
        ->get(route('connections.finish'))
        ->assertRedirect()
        ->headers->get('Location');

    expect(parse_url((string) $location, PHP_URL_HOST))->toBe(parse_url(config()->string('app.url'), PHP_URL_HOST))
        ->and(parse_url((string) $location, PHP_URL_SCHEME))->toBe('https');
});

it('answers only the host Traceway runs on', function (): void {
    $this->app['env'] = 'production';

    try {
        $this->get(rtrim(config()->string('app.url'), '/').'/up')->assertOk();
        $this->get('http://evil.test/up')->assertBadRequest();
    } finally {
        $this->app['env'] = 'testing';
    }
});
