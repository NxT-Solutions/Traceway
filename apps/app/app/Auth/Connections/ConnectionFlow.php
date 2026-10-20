<?php

declare(strict_types=1);

namespace App\Auth\Connections;

use App\Account\ConnectedIdentity;
use App\Account\ConnectionProvider;
use App\Account\Email;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;

/**
 * What a round trip through a service's sign-in page keeps in the rider's session: why it started, an identity
 * waiting for the rider to finish signing up, and one waiting for them to log in to the account it belongs to.
 * The session stores plain values only, and anything holding keys is encrypted first.
 */
final readonly class ConnectionFlow
{
    /**
     * How long an answer, or a waiting identity, stays good.
     */
    public const int MINUTES = 15;

    private const string FLOW = 'connections.flow';

    private const string PENDING = 'connections.pending';

    private const string LINK = 'connections.link';

    private const string METHOD = 'connections.method';

    private const string SENT = 'connections.sent';

    public function __construct(private Session $session) {}

    /**
     * Notes why the rider is about to leave for the service.
     */
    public function start(ConnectionIntent $connectionIntent, ConnectionProvider $connectionProvider, ?int $riderId = null, bool $remember = false, ?string $return = null): void
    {
        $this->session->put(self::FLOW, [
            'intent' => $connectionIntent->value,
            'provider' => $connectionProvider->value,
            'rider' => $riderId,
            'remember' => $remember,
            'return' => self::safeReturn($return),
            'at' => now()->getTimestamp(),
        ]);
    }

    /**
     * Takes the note for the service that answered, or null when the answer did not start here, started for
     * another service, or came too late. A note is used once.
     *
     * @return array{intent: ConnectionIntent, rider: int|null, remember: bool, return: string|null}|null
     */
    public function finish(ConnectionProvider $connectionProvider): ?array
    {
        $flow = $this->session->pull(self::FLOW);

        if (! is_array($flow) || ($flow['provider'] ?? null) !== $connectionProvider->value || ! $this->fresh($flow['at'] ?? null)) {
            return null;
        }

        $connectionIntent = is_string($flow['intent'] ?? null) ? ConnectionIntent::tryFrom($flow['intent']) : null;

        if (! $connectionIntent instanceof ConnectionIntent) {
            return null;
        }

        return [
            'intent' => $connectionIntent,
            'rider' => is_int($flow['rider'] ?? null) ? $flow['rider'] : null,
            'remember' => ($flow['remember'] ?? false) === true,
            'return' => is_string($flow['return'] ?? null) ? self::safeReturn($flow['return']) : null,
        ];
    }

    /**
     * Keeps a new rider's identity while they give or confirm their email and name.
     */
    public function awaitSignUp(ConnectedIdentity $connectedIdentity, bool $remember): void
    {
        $this->session->forget(self::SENT);
        $this->session->put(self::PENDING, $this->seal([
            'identity' => $connectedIdentity->toArray(),
            'remember' => $remember,
            'at' => now()->getTimestamp(),
        ]));
    }

    /**
     * @return array{identity: ConnectedIdentity, remember: bool}|null
     */
    public function pendingSignUp(): ?array
    {
        $pending = $this->open($this->session->get(self::PENDING));

        if ($pending === null || ! $this->fresh($pending['at'] ?? null) || ! is_array($pending['identity'] ?? null)) {
            $this->session->forget(self::PENDING);

            return null;
        }

        $connectedIdentity = ConnectedIdentity::fromArray($pending['identity']);

        return $connectedIdentity instanceof ConnectedIdentity
            ? ['identity' => $connectedIdentity, 'remember' => ($pending['remember'] ?? false) === true]
            : null;
    }

    public function forgetSignUp(): void
    {
        $this->session->forget(self::PENDING);
    }

    /**
     * Notes that the link to finish the sign-up went out, so sending the form again from the browser's history
     * says so instead of that the sign-up ran out. Only the shortened address is kept.
     */
    public function signUpSent(Email $email): void
    {
        $this->session->put(self::SENT, ['email' => self::shortEmail($email), 'at' => now()->getTimestamp()]);
    }

    /**
     * The shortened address the link went to, while that link still works.
     */
    public function sentSignUpTo(): ?string
    {
        $sent = $this->session->get(self::SENT);

        if (! is_array($sent) || ! is_string($sent['email'] ?? null) || ! is_int($sent['at'] ?? null) || $sent['at'] < now()->getTimestamp() - PendingSignUps::MINUTES * 60) {
            $this->session->forget(self::SENT);

            return null;
        }

        return $sent['email'];
    }

    /**
     * Keeps the identity of a rider who already has a Traceway account with this email, until they log in to
     * it. Logging in to that account, and no other, connects it.
     */
    public function awaitLogin(ConnectedIdentity $connectedIdentity, Email $email): void
    {
        $this->session->put(self::LINK, $this->seal([
            'identity' => $connectedIdentity->toArray(),
            'email' => $email->value,
            'at' => now()->getTimestamp(),
        ]));
    }

    /**
     * @return array{identity: ConnectedIdentity, email: Email}|null
     */
    public function pendingLink(): ?array
    {
        $pending = $this->open($this->session->get(self::LINK));

        if ($pending === null || ! $this->fresh($pending['at'] ?? null) || ! is_array($pending['identity'] ?? null) || ! is_string($pending['email'] ?? null)) {
            $this->session->forget(self::LINK);

            return null;
        }

        $connectedIdentity = ConnectedIdentity::fromArray($pending['identity']);

        try {
            $email = new Email($pending['email']);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $connectedIdentity instanceof ConnectedIdentity ? ['identity' => $connectedIdentity, 'email' => $email] : null;
    }

    public function forgetLink(): void
    {
        $this->session->forget(self::LINK);
    }

    /**
     * Notes which service the rider logs in with, for the "Last used" mark, across a two-factor challenge.
     */
    public function signingInWith(ConnectionProvider $connectionProvider): void
    {
        $this->session->put(self::METHOD, $connectionProvider->value);
    }

    public function pullSigningInWith(): ?ConnectionProvider
    {
        $method = $this->session->pull(self::METHOD);

        return is_string($method) ? ConnectionProvider::tryFrom($method) : null;
    }

    /**
     * The address with most of its first part hidden, like a•••••@example.com, in case someone else uses the
     * computer next.
     */
    public static function shortEmail(Email $email): string
    {
        [$local, $domain] = explode('@', $email->value, 2);

        return mb_substr($local, 0, 1).str_repeat('•', max(1, min(6, mb_strlen($local) - 1))).'@'.$domain;
    }

    /**
     * Only a path on this site, so a crafted link cannot send the rider elsewhere after they connect.
     */
    public static function safeReturn(mixed $return): ?string
    {
        if (! is_string($return) || ! str_starts_with($return, '/') || str_starts_with($return, '//') || str_contains($return, '\\') || preg_match('/[\x00-\x1F\x7F]/', $return) === 1) {
            return null;
        }

        return $return;
    }

    private function fresh(mixed $at): bool
    {
        return is_int($at) && $at >= now()->getTimestamp() - self::MINUTES * 60;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function seal(array $values): string
    {
        return Crypt::encryptString(json_encode($values, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<mixed>|null
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

        return is_array($values) ? $values : null;
    }
}
