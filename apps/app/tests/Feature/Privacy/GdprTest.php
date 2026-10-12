<?php

declare(strict_types=1);

use App\Mail\AccountMessage;
use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('keeps the password and sign-in secrets out of the download', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'owner@traceway.test',
        'password' => 'password',
        'remember_token' => 'remember-token-should-stay',
        'two_factor_secret' => 'totp-secret-should-stay',
        'two_factor_recovery_codes' => 'recovery-code-should-stay',
    ]);
    SavedRoute::factory()->create([
        'user_id' => $user->id,
        'name' => 'Morning loop',
    ]);

    $response = $this->actingAs($user)->get(route('privacy.export'));
    $download = $response->streamedContent();
    $stored = $user->fresh();

    $response->assertOk();
    expect($download)->toContain('Morning loop')
        ->toContain('owner@traceway.test')
        ->not->toContain('totp-secret-should-stay')
        ->not->toContain('recovery-code-should-stay')
        ->not->toContain('remember-token-should-stay')
        ->not->toContain((string) $stored?->password)
        ->not->toContain((string) $stored?->getRawOriginal('two_factor_secret'))
        ->not->toContain((string) $stored?->getRawOriginal('two_factor_recovery_codes'));

    $document = json_decode($download, associative: true);
    expect($document['withheld'])->toBe([
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ])->and(array_keys($document['account']))->not->toContain('password', 'two_factor_secret', 'remember_token');

    Mail::assertQueued(AccountMessage::class, function (AccountMessage $accountMessage) use ($stored): bool {
        $attachment = json_encode($accountMessage->attachment);

        return $accountMessage->template === 'data-export'
            && is_string($attachment)
            && $stored instanceof User
            && ! str_contains($attachment, 'totp-secret-should-stay')
            && ! str_contains($attachment, (string) $stored->password);
    });
})->group('gdpr');

it('gives a person only their own download', function (): void {
    $owner = User::factory()->create(['email' => 'owner-private@traceway.test']);
    $other = User::factory()->create(['email' => 'other@traceway.test']);
    SavedRoute::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Private loop',
    ]);

    $download = $this->actingAs($other)->get(route('privacy.export'))->assertOk()->streamedContent();

    expect($download)->toContain('other@traceway.test')
        ->not->toContain('owner-private@traceway.test')
        ->not->toContain('Private loop');
})->group('gdpr');

it('sends a guest away from the download', function (): void {
    $this->get(route('privacy.export'))->assertRedirect();
})->group('gdpr');

it('deletes that account and leaves everyone else in place', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    $other = User::factory()->create();
    $ownRoute = SavedRoute::factory()->create(['user_id' => $user->id]);
    $otherRoute = SavedRoute::factory()->create(['user_id' => $other->id, 'name' => 'Kept loop']);

    $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ])->assertRedirect('/');

    expect(User::query()->find($user->id))->toBeNull()
        ->and(SavedRoute::query()->find($ownRoute->id))->toBeNull()
        ->and(User::query()->find($other->id))->not->toBeNull()
        ->and(SavedRoute::query()->find($otherRoute->id)?->name)->toBe('Kept loop');
})->group('gdpr');

it('does not put the owner on a shared course', function (): void {
    $user = User::factory()->create(['email' => 'owner-private@traceway.test', 'name' => 'Owner']);
    $route = SavedRoute::factory()->create([
        'user_id' => $user->id,
        'name' => 'Morning loop',
    ]);

    $this->get(URL::signedRoute('routes.share', $route))
        ->assertOk()
        ->assertDontSee('owner-private@traceway.test', false)
        ->assertInertia(fn ($page) => $page
            ->missing('route.email')
            ->missing('route.user_id'));

    expect($this->get(URL::signedRoute('routes.share.gpx', $route))->getContent())
        ->not->toContain('owner-private@traceway.test');
})->group('gdpr');

it('does not put the new password in the welcome message', function (): void {
    Mail::fake();

    $this->post(route('register.store'), [
        'name' => 'New person',
        'email' => 'new-person@traceway.test',
        'password' => 'a-secret-password',
        'password_confirmation' => 'a-secret-password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'new-person@traceway.test')->firstOrFail();

    $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]))->assertRedirect();

    Mail::assertQueued(AccountMessage::class, function (AccountMessage $accountMessage): bool {
        $encoded = json_encode($accountMessage->values);

        return $accountMessage->template === 'welcome'
            && is_string($encoded)
            && ! str_contains($encoded, 'a-secret-password');
    });
})->group('gdpr');

it('puts every leg of a multisport plan, swim included, in the download', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    SavedRoute::factory()->create([
        'user_id' => $user->id,
        'name' => 'Sunday triathlon',
        'layout' => 'chain',
        'legs' => [
            ['discipline' => 'swim', 'distance_metres' => 750, 'ascent_metres' => 0, 'coordinates' => [[4.891, 51.101], [4.895, 51.103]], 'color' => null],
            ['discipline' => 'road', 'distance_metres' => 20_000, 'ascent_metres' => 40, 'coordinates' => [[4.9, 51.1], [4.93, 51.12]], 'color' => '#db2777'],
        ],
    ]);

    $document = json_decode($this->actingAs($user)->get(route('privacy.export'))->assertOk()->streamedContent(), associative: true);
    $route = $document['saved_routes'][0];

    expect($route['layout'])->toBe('chain')
        ->and($route['legs'])->toHaveCount(2)
        ->and($route['legs'][0]['discipline'])->toBe('swim')
        ->and($route['legs'][0]['coordinates'])->toBe([[4.891, 51.101], [4.895, 51.103]])
        ->and($route['legs'][1]['color'])->toBe('#db2777');
})->group('gdpr');

it('does not put the owner on a shared multisport course or its leg files', function (): void {
    $user = User::factory()->create(['email' => 'owner-private@traceway.test', 'name' => 'Owner']);
    $route = SavedRoute::factory()->create([
        'user_id' => $user->id,
        'layout' => 'loops',
        'legs' => [
            ['discipline' => 'road', 'distance_metres' => 20_000, 'ascent_metres' => 40, 'coordinates' => [[4.9, 51.1], [4.93, 51.12], [4.9, 51.1]]],
            ['discipline' => 'run', 'distance_metres' => 5_000, 'ascent_metres' => 10, 'coordinates' => [[4.9, 51.1], [4.91, 51.11], [4.9, 51.1]]],
        ],
    ]);

    $this->get(URL::signedRoute('routes.share', $route))
        ->assertOk()
        ->assertDontSee('owner-private@traceway.test', false)
        ->assertDontSee('Owner', false);

    expect($this->get(URL::signedRoute('routes.share.fit.leg', ['savedRoute' => $route->id, 'leg' => 2]))->getContent())
        ->not->toContain('owner-private@traceway.test')
        ->not->toContain('Owner');
})->group('gdpr');
