<?php

declare(strict_types=1);

use App\Account\ConnectedIdentity;
use App\Account\ConnectionProvider;
use App\Account\Email;
use App\Account\RiderName;
use App\Auth\Connections\PendingSignUps;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;

/**
 * A cache that runs something once, in the middle of reading a waiting sign-up, as a second request would.
 */
function pendingSignUpsCacheWithAnotherRead(): Repository
{
    return new class(new ArrayStore) extends Repository
    {
        public ?Closure $duringRead = null;

        public function get($key, $default = null): mixed
        {
            $value = parent::get($key, $default);

            if (is_string($key) && ! str_ends_with($key, ':used') && $this->duringRead instanceof Closure) {
                $duringRead = $this->duringRead;
                $this->duringRead = null;
                $duringRead();
            }

            return $value;
        }
    };
}

function pendingSignUpsHold(PendingSignUps $pendingSignUps): string
{
    $connectedIdentity = ConnectedIdentity::fromService(ConnectionProvider::OpenStreetMap, '4711', email: null, emailVerified: false, name: null, accountLabel: null, connectionTokens: null, scopes: ['read_prefs']);

    return $pendingSignUps->hold($connectedIdentity, new RiderName('Ann'), new Email('ann@example.com'), remember: false, request: Request::create('/login/finish', 'POST'))['token'];
}

it('gives a link to one of two posts that arrive at the same moment', function (): void {
    $repository = pendingSignUpsCacheWithAnotherRead();
    $pendingSignUps = new PendingSignUps($repository);
    $token = pendingSignUpsHold($pendingSignUps);
    $second = 'not asked';

    $repository->duringRead = function () use ($pendingSignUps, $token, &$second): void {
        $second = $pendingSignUps->take($token);
    };

    $first = $pendingSignUps->take($token);

    expect($first)->toBeArray()
        ->and($first['email'] ?? null)->toEqual(new Email('ann@example.com'))
        ->and($second)->toBeNull()
        ->and($pendingSignUps->take($token))->toBeNull()
        ->and($pendingSignUps->find($token))->toBeNull();
});

it('ties the sign-up to the browser that asked for it', function (): void {
    $pendingSignUps = new PendingSignUps(new Repository(new ArrayStore));
    $connectedIdentity = ConnectedIdentity::fromService(ConnectionProvider::OpenStreetMap, '4711', email: null, emailVerified: false, name: null, accountLabel: null, connectionTokens: null, scopes: ['read_prefs']);
    $held = $pendingSignUps->hold($connectedIdentity, new RiderName('Ann'), new Email('ann@example.com'), remember: false, request: Request::create('/login/finish', 'POST'));
    $pending = $pendingSignUps->find($held['token']);
    $browser = (string) $held['cookie']->getValue();

    expect($pending)->toBeArray();
    assert(is_array($pending));

    expect(PendingSignUps::sameBrowser($pending, Request::create('/', cookies: [PendingSignUps::COOKIE => $browser])))->toBeTrue()
        ->and(PendingSignUps::sameBrowser($pending, Request::create('/')))->toBeFalse()
        ->and(PendingSignUps::sameBrowser($pending, Request::create('/', cookies: [PendingSignUps::COOKIE => str_repeat('a', 64)])))->toBeFalse();

    // A second sign-up from the same browser keeps its code, so both links connect the service there.
    $again = $pendingSignUps->hold($connectedIdentity, new RiderName('Ann'), new Email('ann@example.org'), remember: false, request: Request::create('/login/finish', 'POST', cookies: [PendingSignUps::COOKIE => $browser]));

    expect($again['cookie']->getValue())->toBe($browser);
});
