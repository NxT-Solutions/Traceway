<?php

declare(strict_types=1);

namespace App\Auth\Connections;

use App\Account\ConnectedIdentity;
use App\Account\Email;
use App\Account\RiderName;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * A new rider's sign-up that waits for them to prove the email is theirs. Nobody has shown the address is theirs
 * yet, so no account is opened and no service is connected until the link in the email is used. The sign-up
 * waits here, encrypted since it can hold keys, under a one-use token that only the email carries.
 *
 * A cookie marks the browser that asked for the link. Only that browser connects the service. Someone else who
 * uses the link, such as the owner of an address another person typed, gets the account without that service.
 */
final readonly class PendingSignUps
{
    /**
     * How long the link in the email works.
     */
    public const int MINUTES = 60;

    /**
     * Holds a random code for the browser that asked for the link. Its hash waits with the sign-up.
     */
    public const string COOKIE = 'finish_sign_up';

    public function __construct(private Repository $repository) {}

    /**
     * Keeps the sign-up, and gives the token for the link and the cookie for the browser that asked for it. A
     * browser that asked before keeps its code, so each link it asked for still connects the service there.
     *
     * @return array{token: string, cookie: Cookie}
     */
    public function hold(ConnectedIdentity $connectedIdentity, RiderName $riderName, Email $email, bool $remember, Request $request): array
    {
        $token = Str::random(64);
        $browser = self::browserCode($request) ?? Str::random(64);

        $this->repository->put($this->key($token), Crypt::encryptString(json_encode([
            'identity' => $connectedIdentity->toArray(),
            'name' => $riderName->value,
            'email' => $email->value,
            'remember' => $remember,
            'browser' => hash('sha256', $browser),
        ], JSON_THROW_ON_ERROR)), self::MINUTES * 60);

        return [
            'token' => $token,
            'cookie' => cookie(self::COOKIE, $browser, self::MINUTES, '/', secure: config('session.secure') === true, httpOnly: true, raw: false, sameSite: 'lax'),
        ];
    }

    /**
     * Whether the request comes from the browser that asked for the link.
     *
     * @param  array{browser: string|null}  $pending
     */
    public static function sameBrowser(array $pending, Request $request): bool
    {
        $browser = self::browserCode($request);

        return $pending['browser'] !== null && $browser !== null && hash_equals($pending['browser'], hash('sha256', $browser));
    }

    /**
     * Clears the cookie once the link was used.
     */
    public static function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }

    /**
     * The sign-up for the token, which stays for the rider to finish. Opening the link only shows it, so a mail
     * scanner that opens every link first does not use it up.
     *
     * @return array{identity: ConnectedIdentity, name: RiderName, email: Email, remember: bool, browser: string|null}|null
     */
    public function find(#[SensitiveParameter] string $token): ?array
    {
        return $this->valid($token) ? $this->open($this->repository->get($this->key($token))) : null;
    }

    /**
     * The sign-up for the token, once, or null when it was used or ran out. The token is claimed before the
     * sign-up is read, so two posts of the same link at once cannot both open the account.
     *
     * @return array{identity: ConnectedIdentity, name: RiderName, email: Email, remember: bool, browser: string|null}|null
     */
    public function take(#[SensitiveParameter] string $token): ?array
    {
        if (! $this->valid($token) || ! $this->repository->add($this->key($token).':used', value: true, ttl: self::MINUTES * 60)) {
            return null;
        }

        return $this->open($this->repository->pull($this->key($token)));
    }

    private function valid(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1;
    }

    private static function browserCode(Request $request): ?string
    {
        $browser = $request->cookie(self::COOKIE);

        return is_string($browser) && preg_match('/^[A-Za-z0-9]{64}$/', $browser) === 1 ? $browser : null;
    }

    private function key(string $token): string
    {
        return 'connections:sign-up:'.hash('sha256', $token);
    }

    /**
     * @return array{identity: ConnectedIdentity, name: RiderName, email: Email, remember: bool, browser: string|null}|null
     */
    private function open(mixed $sealed): ?array
    {
        if (! is_string($sealed) || $sealed === '') {
            return null;
        }

        try {
            $values = json_decode(Crypt::decryptString($sealed), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($values) || ! is_array($values['identity'] ?? null) || ! is_string($values['name'] ?? null) || ! is_string($values['email'] ?? null)) {
            return null;
        }

        $connectedIdentity = ConnectedIdentity::fromArray($values['identity']);

        if (! $connectedIdentity instanceof ConnectedIdentity) {
            return null;
        }

        try {
            return [
                'identity' => $connectedIdentity,
                'name' => new RiderName($values['name']),
                'email' => new Email($values['email']),
                'remember' => ($values['remember'] ?? false) === true,
                'browser' => is_string($values['browser'] ?? null) ? $values['browser'] : null,
            ];
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
