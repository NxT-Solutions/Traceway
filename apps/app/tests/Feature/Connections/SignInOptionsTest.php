<?php

declare(strict_types=1);

use App\Account\ConnectedIdentity;
use App\Account\ConnectionProvider;
use App\Account\Email;
use App\Auth\Connections\ConnectionFlow;
use App\Auth\Connections\LastSignIn;
use App\Models\ConnectedAccount;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DrawnPage;

uses(RefreshDatabase::class);

/**
 * Gives Traceway keys for these services, which turns them on.
 */
function signInOptionsWith(string ...$slugs): void
{
    foreach ($slugs as $slug) {
        config(["services.{$slug}.client_id" => $slug.'-id', "services.{$slug}.client_secret" => $slug.'-secret']);
    }
}

function signInOptionsConnectRiders(string $provider, int $riders): void
{
    ConnectedAccount::factory()->count($riders)->signInOnly($provider)->create();
}

/**
 * The services a page shows as buttons, in order.
 *
 * @return list<mixed>
 */
function signInOptionsFeatured(TestResponse $testResponse): array
{
    return array_column((array) $testResponse->inertiaProps('connections.featured'), 'slug');
}

it('offers the four services riders use most on the log in page, and counts them once an hour', function (): void {
    signInOptionsWith('google', 'garmin_connect', 'github', 'discord', 'x');
    signInOptionsConnectRiders('github', 2);
    signInOptionsConnectRiders('discord', 1);

    $first = $this->get(route('login'))->assertOk();

    expect(signInOptionsFeatured($first))->toBe(['github', 'discord', 'google', 'garmin_connect'])
        ->and($first->inertiaProps('connections.more'))->toBe(1)
        ->and($first->inertiaProps('connections.lastUsed'))->toBeNull()
        ->and($first->inertiaProps('connections.featured.0'))->toBe(['slug' => 'github', 'label' => 'GitHub', 'note' => null]);

    signInOptionsConnectRiders('x', 3);

    expect(signInOptionsFeatured($this->get(route('login'))))->toBe(['github', 'discord', 'google', 'garmin_connect']);

    $this->travel(61)->minutes();

    expect(signInOptionsFeatured($this->get(route('login'))))->toBe(['x', 'github', 'discord', 'google']);
});

it('does not count connections from before that cannot log anyone in yet', function (): void {
    signInOptionsWith('google', 'garmin_connect', 'facebook', 'wahoo', 'ride_with_gps');
    ConnectedAccount::factory()->count(3)->withoutIdentity()->create();

    expect(signInOptionsFeatured($this->get(route('login'))))->toBe(['google', 'garmin_connect', 'facebook', 'wahoo']);
});

it('puts the service this browser last logged in with first', function (): void {
    signInOptionsWith('google', 'garmin_connect', 'github', 'discord', 'x');

    $testResponse = $this->withCookie(LastSignIn::COOKIE, 'discord')->get(route('login'));

    expect(signInOptionsFeatured($testResponse))->toBe(['discord', 'google', 'garmin_connect', 'x'])
        ->and($testResponse->inertiaProps('connections.more'))->toBe(1)
        ->and($testResponse->inertiaProps('connections.lastUsed'))->toBe('discord');
});

it('keeps the usual order for a last used way that is not a service', function (string $cookie, ?string $lastUsed): void {
    signInOptionsWith('google', 'garmin_connect', 'github', 'discord', 'x');

    $testResponse = $this->withCookie(LastSignIn::COOKIE, $cookie)->get(route('login'));

    expect(signInOptionsFeatured($testResponse))->toBe(['google', 'garmin_connect', 'x', 'github'])
        ->and($testResponse->inertiaProps('connections.lastUsed'))->toBe($lastUsed);
})->with([
    'a password' => ['password', 'password'],
    'a passkey' => ['passkey', 'passkey'],
    'something Traceway does not know' => ['myspace', null],
]);

it('offers no services when none is set up', function (): void {
    $testResponse = $this->get(route('login'))->assertOk();

    expect($testResponse->inertiaProps('connections'))->toBe(['featured' => [], 'more' => 0, 'lastUsed' => null])
        ->and($testResponse->inertiaProps('pendingConnection'))->toBeNull();
});

it('offers the same services on the sign up page', function (): void {
    signInOptionsWith('google', 'github');

    $testResponse = $this->withCookie(LastSignIn::COOKIE, 'github')->get(route('register'))->assertOk();

    expect(signInOptionsFeatured($testResponse))->toBe(['github', 'google'])
        ->and($testResponse->inertiaProps('connections.more'))->toBe(0)
        ->and($testResponse->inertiaProps('connections.lastUsed'))->toBe('github');
});

it('lists every service set up here in its group behind More ways to log in', function (): void {
    signInOptionsWith('google', 'garmin_connect', 'wahoo', 'openstreetmap');

    $this->withCookie(LastSignIn::COOKIE, 'wahoo')
        ->get(route('login.options'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/sign-in-options')
            ->where('lastUsed', 'wahoo')
            ->where('groups', [
                ['key' => 'sport', 'label' => 'Sport apps', 'providers' => [
                    ['slug' => 'garmin_connect', 'label' => 'Garmin Connect', 'note' => null],
                    ['slug' => 'wahoo', 'label' => 'Wahoo', 'note' => null],
                ]],
                ['key' => 'accounts', 'label' => 'Everyday accounts', 'providers' => [
                    ['slug' => 'google', 'label' => 'Google', 'note' => null],
                ]],
                ['key' => 'maps', 'label' => 'Maps', 'providers' => [
                    ['slug' => 'openstreetmap', 'label' => 'OpenStreetMap', 'note' => null],
                ]],
            ]));
});

it('sends the rider back to log in when no service is set up', function (): void {
    $this->get(route('login.options'))->assertRedirect(route('login'));
});

it('says log in and forgets Remember me unless the rider asked otherwise', function (): void {
    signInOptionsWith('google');

    $this->get(route('login.options'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/sign-in-options')
            ->where('remember', expected: false)
            ->where('intent', 'login'));
});

it('keeps Remember me for every service on More ways to log in', function (): void {
    signInOptionsWith('google');

    $this->get(route('login.options', ['remember' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('remember', expected: true)
            ->where('intent', 'login'));
});

it('reads as signing up when the rider came from the sign up page', function (): void {
    signInOptionsWith('google');

    $this->get(route('login.options', ['intent' => 'register']))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('remember', expected: false)
            ->where('intent', 'register'));
});

it('reads as logging in for an intent it does not know', function (mixed $intent): void {
    signInOptionsWith('google');

    $this->get(route('login.options', ['intent' => $intent]))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('intent', 'login'));
})->with([
    'another word' => ['admin'],
    'a list' => [['register']],
]);

it('sends the rider back to sign up when no service is set up and they came from there', function (): void {
    $this->get(route('login.options', ['intent' => 'register']))->assertRedirect(route('register'));
});

/**
 * The log in page as the server draws it. A failed sign-in is Google sending the rider back to a browser that did
 * not start one.
 */
function signInOptionsLoginAfter(bool $failedSignIn = false, ?string $status = null): HTMLDocument
{
    signInOptionsWith('google');

    if ($status !== null) {
        test()->withSession(['status' => $status]);
    }

    if ($failedSignIn) {
        test()->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']))
            ->assertRedirect(route('login'));
    }

    return DrawnPage::of(test()->get(route('login'))->assertOk());
}

/**
 * The note above the form that holds these words.
 */
function signInOptionsNote(HTMLDocument $htmlDocument, string $words): Element
{
    $nodeList = $htmlDocument->querySelectorAll('.notice');

    foreach ($nodeList as $note) {
        if (str_contains((string) $note->textContent, $words)) {
            return $note;
        }
    }

    throw new RuntimeException('No note says: '.$words);
}

/**
 * Google came back with the email of an account the rider already has, so it waits until they log in.
 */
function signInOptionsGoogleWaits(): void
{
    signInOptionsWith('google');
    test()->get(route('login'));
    new ConnectionFlow(resolve('session.store'))->awaitLogin(
        new ConnectedIdentity(ConnectionProvider::Google, '3003', new Email('noah.rider@gmail.com'), emailVerified: true),
        new Email('noah.rider@gmail.com'),
    );
}

it('reads why a service did not log the rider in once, by focus and not as an alert too', function (): void {
    $element = signInOptionsNote(signInOptionsLoginAfter(failedSignIn: true), 'That took too long');

    expect($element->getAttribute('tabindex'))->toBe('-1')
        ->and($element->hasAttribute('role'))->toBeFalse();
});

it('reads the news after Email me a link once, by focus and not as a live note too', function (): void {
    $element = signInOptionsNote(signInOptionsLoginAfter(status: 'Check your email. The link opens your account.'), 'Check your email.');

    expect($element->getAttribute('tabindex'))->toBe('-1')
        ->and($element->hasAttribute('role'))->toBeFalse();
});

it('keeps a note live when another note takes focus first', function (): void {
    $htmlDocument = signInOptionsLoginAfter(failedSignIn: true, status: 'Check your email. The link opens your account.');

    expect(signInOptionsNote($htmlDocument, 'That took too long')->hasAttribute('role'))->toBeFalse()
        ->and(signInOptionsNote($htmlDocument, 'Check your email.')->getAttribute('role'))->toBe('status');
});

it('reads a service waiting to connect once, by focus and not as a live note too', function (): void {
    signInOptionsGoogleWaits();

    $element = signInOptionsNote(DrawnPage::of($this->get(route('login'))->assertOk()), 'Log in to it once to connect Google.');

    expect($element->getAttribute('tabindex'))->toBe('-1')
        ->and($element->hasAttribute('role'))->toBeFalse();

    $behindAnError = signInOptionsNote(signInOptionsLoginAfter(failedSignIn: true), 'Log in to it once to connect Google.');

    expect($behindAnError->getAttribute('role'))->toBe('status');
});
