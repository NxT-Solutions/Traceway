<?php

declare(strict_types=1);

namespace App\Auth\Connections;

use App\Account\ConnectedIdentity;
use App\Account\ConnectionFailed;
use App\Account\ConnectionProblem;
use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Contracts\Auth\IdentityProviders;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as OAuth2User;
use RuntimeException;
use Throwable;

/**
 * Each service's own sign-in, through Socialite and the SocialiteProviders packages. This is the one place the
 * handshake leaves Saloon: Socialite keeps the state and the PKCE verifier in the session, checks Apple's signed
 * token, and speaks each service's quirks. What Traceway does with a connection afterwards, such as sending a
 * route or withdrawing its access, goes through the Saloon connectors in App\Integrations.
 */
final readonly class SocialiteIdentityProviders implements IdentityProviders
{
    /**
     * Answers that mean the rider chose not to allow Traceway, as each service spells it.
     */
    private const array CANCELLED = ['access_denied', 'user_cancelled_authorize', 'user_denied', 'consent_required', 'login_required'];

    public function __construct(
        private Factory $factory,
        private Repository $repository,
        private Request $request,
    ) {}

    /**
     * @return list<ConnectionProvider>
     */
    public function enabled(): array
    {
        $enabled = array_values(array_filter(ConnectionProvider::cases(), $this->isEnabled(...)));

        usort($enabled, static fn (ConnectionProvider $a, ConnectionProvider $b): int => $a->rank() <=> $b->rank());

        return $enabled;
    }

    public function isEnabled(ConnectionProvider $connectionProvider): bool
    {
        $config = $this->repository->get('services.'.$connectionProvider->value);

        if (! is_array($config)) {
            return false;
        }

        // Apple signs a fresh client secret with the team's private key for every request.
        $required = $connectionProvider === ConnectionProvider::Apple
            ? ['client_id', 'team_id', 'key_id', 'private_key']
            : ['client_id', 'client_secret'];

        return array_all($required, fn ($key): bool => is_string($config[$key] ?? null) && trim($config[$key]) !== '');
    }

    public function redirectUrl(ConnectionProvider $connectionProvider): string
    {
        try {
            return $this->driver($connectionProvider)->redirect()->getTargetUrl();
        } catch (Throwable $throwable) {
            report($throwable);

            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Unavailable, $throwable);
        }
    }

    public function identity(ConnectionProvider $connectionProvider): ConnectedIdentity
    {
        $error = $this->request->input('error');

        if (is_string($error) && $error !== '') {
            throw ConnectionFailed::because($connectionProvider, in_array($error, self::CANCELLED, strict: true) ? ConnectionProblem::Cancelled : ConnectionProblem::Refused);
        }

        $code = $this->request->input('code');

        if (! is_string($code) || $code === '') {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Cancelled);
        }

        try {
            $user = $this->driver($connectionProvider)->user();
        } catch (InvalidStateException $invalidStateException) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Expired, $invalidStateException);
        } catch (ClientException $clientException) {
            // A code used twice or too late, or a key the service no longer accepts.
            $failure = $this->withoutKeys($connectionProvider, $clientException);
            report($failure);

            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Refused, $failure);
        } catch (GuzzleException $guzzleException) {
            $failure = $this->withoutKeys($connectionProvider, $guzzleException);
            report($failure);

            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Unavailable, $failure);
        } catch (Throwable $throwable) {
            // The service answered something that is not an account.
            report($throwable);

            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Incomplete, $throwable);
        }

        return $this->connectedIdentity($connectionProvider, $user);
    }

    /**
     * The failed request without its query or the service's answer. Facebook and Instagram take the rider's access
     * token in the query, and Guzzle puts the whole address in its message, so the log and Sentry would keep it.
     */
    private function withoutKeys(ConnectionProvider $connectionProvider, Throwable $throwable): Throwable
    {
        if (! $throwable instanceof TransferException) {
            return $throwable;
        }

        $request = $throwable->getRequest();
        $status = $throwable instanceof ResponseException ? $throwable->getResponse()->getStatusCode() : null;

        return new RuntimeException(sprintf(
            '%s %s to %s %s.',
            $connectionProvider->label(),
            $status === null ? 'did not answer' : 'answered '.$status,
            $request->getMethod(),
            (string) $request->getUri()->withQuery('')->withFragment('')->withUserInfo(''),
        ), $throwable->getCode());
    }

    private function connectedIdentity(ConnectionProvider $connectionProvider, User $user): ConnectedIdentity
    {
        // Services send their number as text or as a number, whatever Socialite's type says.
        $id = $this->text($user instanceof OAuth2User ? $user->id : $user->getId());

        if ($id === null) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Incomplete);
        }

        // A user built without the service's answer, such as Socialite's test double, has none.
        $raw = $user instanceof OAuth2User ? $user->getRaw() : null;
        $raw = is_array($raw) ? $raw : [];

        $email = $this->text($user->getEmail());
        $nickname = $this->text($user->getNickname());
        $name = $this->text($user->getName());

        try {
            return ConnectedIdentity::fromService(
                $connectionProvider,
                $id,
                $email,
                $this->confirmsEmail($connectionProvider, $email, $raw),
                $name,
                $this->accountLabel($connectionProvider, $email, $nickname, $name),
                $connectionProvider->keepsKeys() ? $this->tokens($user) : null,
                $this->scopes($user),
            );
        } catch (Throwable $throwable) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::Incomplete, $throwable);
        }
    }

    /**
     * True only when the service itself says it confirmed the address. Traceway never links an existing account
     * by email alone, but a confirmed address opens a new account without a confirmation email.
     *
     * @param  array<mixed>  $raw
     */
    private function confirmsEmail(ConnectionProvider $connectionProvider, ?string $email, array $raw): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        return match ($connectionProvider) {
            ConnectionProvider::Google => ($raw['email_verified'] ?? false) === true,
            // Apple sends the flag as a boolean or as the word.
            ConnectionProvider::Apple => in_array($raw['email_verified'] ?? false, [true, 'true'], strict: true),
            ConnectionProvider::Discord => ($raw['verified'] ?? false) === true,
            // Socialite returns only GitHub's primary address when it is verified, and X's confirmed address.
            ConnectionProvider::GitHub, ConnectionProvider::X => true,
            default => false,
        };
    }

    /**
     * What the connections page shows to say which account at the service is connected.
     */
    private function accountLabel(ConnectionProvider $connectionProvider, ?string $email, ?string $nickname, ?string $name): ?string
    {
        return match ($connectionProvider) {
            // Instagram's provider hands the username over as the name, and sends no nickname or email.
            ConnectionProvider::Instagram => $nickname ?? $name,
            ConnectionProvider::X, ConnectionProvider::GitHub, ConnectionProvider::Discord, ConnectionProvider::OpenStreetMap => $nickname ?? $email,
            default => $email ?? $nickname,
        };
    }

    private function tokens(User $user): ?ConnectionTokens
    {
        $token = $user instanceof OAuth2User ? $this->text($user->token) : null;

        if (! $user instanceof OAuth2User || $token === null) {
            return null;
        }

        $expiresIn = $this->seconds($user->expiresIn);

        return new ConnectionTokens(
            $token,
            $this->text($user->refreshToken),
            $expiresIn === null ? null : now()->getTimestamp() + $expiresIn,
        );
    }

    /**
     * A value a service sent as text, or as a number, or left out.
     */
    private function text(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function seconds(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @return list<string>
     */
    private function scopes(User $user): array
    {
        return $user instanceof OAuth2User ? $this->names($user->approvedScopes) : [];
    }

    /**
     * Each name once, from a list a service may also leave out. Apple's provider never sets the scopes.
     *
     * @return list<string>
     */
    private function names(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => is_string($value) ? trim($value) : '', $values),
            static fn (string $value): bool => $value !== '',
        )));
    }

    /**
     * The service's driver, asking for as little as each one allows, with PKCE where the service supports it.
     */
    private function driver(ConnectionProvider $connectionProvider): Provider
    {
        $provider = $this->factory->driver($connectionProvider->value);

        if (! $provider instanceof AbstractProvider) {
            return $provider;
        }

        if ($provider instanceof GoogleProvider) {
            // A shared computer may be signed in to someone else's Google account.
            $provider->with(['prompt' => 'select_account']);
        }

        if ($provider instanceof FacebookProvider) {
            $provider->fields(['name', 'email']);
        }

        return match ($connectionProvider) {
            ConnectionProvider::Google, ConnectionProvider::GitHub, ConnectionProvider::Discord, ConnectionProvider::OpenStreetMap => $provider->enablePKCE(),
            default => $provider,
        };
    }
}
