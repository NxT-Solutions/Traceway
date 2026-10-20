<?php

declare(strict_types=1);

namespace App\Account;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Who a rider is at a connected service, as the service told Traceway after they allowed it. The service's own
 * number for the rider is what links the two accounts. An email is trusted as confirmed only when the service
 * itself says it confirmed it. A service that does not let Traceway keep the rider's name or username gives
 * neither, so the name is not offered for the account either.
 */
final readonly class ConnectedIdentity
{
    public string $providerUserId;

    public ?string $accountLabel;

    public bool $emailVerified;

    public ?ConnectionTokens $tokens;

    /**
     * @param  list<string>  $scopes  what the rider allowed, as the service names it
     */
    public function __construct(
        public ConnectionProvider $provider,
        string $providerUserId,
        public ?Email $email = null,
        bool $emailVerified = false,
        public ?RiderName $name = null,
        ?string $accountLabel = null,
        #[SensitiveParameter] ?ConnectionTokens $connectionTokens = null,
        public array $scopes = [],
    ) {
        $id = trim($providerUserId);

        throw_if($id === '' || mb_strlen($id) > 191 || preg_match('/[\x00-\x1F\x7F]/', $id) === 1, InvalidArgumentException::class, 'A connected account needs the number the service gave it.');

        $this->providerUserId = $id;
        $this->emailVerified = $email instanceof Email && $emailVerified;
        $this->accountLabel = $provider->keepsAccountLabel() ? $this->label($accountLabel) : null;
        $this->tokens = $provider->keepsKeys() ? $connectionTokens : null;
    }

    /**
     * Builds the identity from what a service sent, dropping a name or an email that is not one, rather than
     * refusing the rider. The name goes too when the service does not let Traceway keep it.
     *
     * @param  list<string>  $scopes
     */
    public static function fromService(
        ConnectionProvider $connectionProvider,
        string $providerUserId,
        ?string $email,
        bool $emailVerified,
        ?string $name,
        ?string $accountLabel,
        #[SensitiveParameter] ?ConnectionTokens $connectionTokens,
        array $scopes,
    ): self {
        return new self(
            $connectionProvider,
            $providerUserId,
            self::email($email),
            $emailVerified,
            $connectionProvider->keepsAccountLabel() ? self::name($name) : null,
            $accountLabel,
            $connectionTokens,
            $scopes,
        );
    }

    /**
     * The identity as plain values, to wait in the session while the rider finishes signing up. The caller
     * encrypts it, since it can hold keys.
     *
     * @return array{provider: string, id: string, email: string|null, email_verified: bool, name: string|null, label: string|null, access_token: string|null, refresh_token: string|null, expires_at: int|null, scopes: list<string>}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'id' => $this->providerUserId,
            'email' => $this->email?->value,
            'email_verified' => $this->emailVerified,
            'name' => $this->name?->value,
            'label' => $this->accountLabel,
            'access_token' => $this->tokens?->accessToken,
            'refresh_token' => $this->tokens?->refreshToken,
            'expires_at' => $this->tokens?->expiresAt,
            'scopes' => $this->scopes,
        ];
    }

    /**
     * The identity back from toArray(), or null when the values are not one.
     *
     * @param  array<mixed>  $values
     */
    public static function fromArray(array $values): ?self
    {
        $provider = is_string($values['provider'] ?? null) ? ConnectionProvider::tryFrom($values['provider']) : null;
        $id = $values['id'] ?? null;

        if (! $provider instanceof ConnectionProvider || ! is_string($id) || trim($id) === '') {
            return null;
        }

        $accessToken = $values['access_token'] ?? null;
        $refreshToken = $values['refresh_token'] ?? null;
        $expiresAt = $values['expires_at'] ?? null;
        $scopes = is_array($values['scopes'] ?? null) ? array_values(array_filter($values['scopes'], is_string(...))) : [];

        return self::fromService(
            $provider,
            $id,
            is_string($values['email'] ?? null) ? $values['email'] : null,
            ($values['email_verified'] ?? false) === true,
            is_string($values['name'] ?? null) ? $values['name'] : null,
            is_string($values['label'] ?? null) ? $values['label'] : null,
            is_string($accessToken) && $accessToken !== '' ? new ConnectionTokens(
                $accessToken,
                is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null,
                is_int($expiresAt) ? $expiresAt : null,
            ) : null,
            $scopes,
        );
    }

    /**
     * True when this is the same account at the same service.
     */
    public function sameAs(self $other): bool
    {
        return $this->provider === $other->provider && $this->providerUserId === $other->providerUserId;
    }

    private static function email(?string $email): ?Email
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        try {
            return new Email($email);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function name(?string $name): ?RiderName
    {
        $clean = $name === null ? '' : trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name));

        if ($clean === '') {
            return null;
        }

        try {
            return new RiderName(mb_substr($clean, 0, 255));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function label(?string $label): ?string
    {
        $clean = $label === null ? '' : trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $label));

        return $clean === '' ? null : mb_substr($clean, 0, 191);
    }
}
