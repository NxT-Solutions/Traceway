<?php

declare(strict_types=1);

use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Adds passkeys to the rider's account, as the browser would after "Add passkey".
 */
function lastWayInPasskeys(User $user, int $count): void
{
    for ($number = 1; $number <= $count; $number++) {
        DB::table('passkeys')->insert([
            'user_id' => $user->id,
            'name' => 'Passkey '.$number,
            'credential_id' => sprintf('credential-%d-%d', $user->id, $number),
            'credential' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/**
 * The security page, with the password confirmed a moment ago.
 */
function lastWayInPage(User $user): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('security.edit'))
        ->assertOk();
}

/**
 * Whether the security page says the rider's one passkey is their only way to log in.
 */
function lastWayInShown(User $user, bool $shown): void
{
    lastWayInPage($user)->assertInertia(fn (Assert $assert): Assert => $assert
        ->component('settings/security')
        ->where('passkeyIsLastWayIn', $shown));
}

it('says the one passkey of a rider without a password or a connection is their only way to log in', function (): void {
    $user = User::factory()->create(['password' => null]);
    lastWayInPasskeys($user, 1);

    lastWayInShown($user, shown: true);
});

it('lets a rider remove a passkey while another way still logs them in', function (array $attributes, int $passkeys): void {
    $user = User::factory()->create($attributes);
    lastWayInPasskeys($user, $passkeys);

    lastWayInShown($user, shown: false);
})->with([
    'a password' => [[], 1],
    'a second passkey' => [['password' => null], 2],
    'no passkey at all' => [['password' => null], 0],
]);

it('counts a connection to a service set up here as a way to log in', function (): void {
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);
    $user = User::factory()->create(['password' => null]);
    lastWayInPasskeys($user, 1);
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create();

    lastWayInShown($user, shown: false);
});

it('does not count a connection that cannot log the rider in here', function (string $case): void {
    config(['services.wahoo.client_id' => 'wahoo-id', 'services.wahoo.client_secret' => 'wahoo-secret']);
    $user = User::factory()->create(['password' => null]);
    lastWayInPasskeys($user, 1);

    match ($case) {
        'not set up here' => ConnectedAccount::factory()->for($user)->signInOnly('github')->create(),
        'carried over without a number' => ConnectedAccount::factory()->for($user)->withoutIdentity()->create(),
    };

    lastWayInShown($user, shown: true);
})->with(['not set up here', 'carried over without a number']);
