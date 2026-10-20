<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\ConnectionProblem;
use App\Account\ConnectionProvider;
use App\Contracts\Auth\IdentityProviders;
use App\Queries\RowReader;
use Illuminate\Support\Facades\DB;

/**
 * A rider's connected accounts and the ones they could add. Keys are never selected.
 */
final readonly class ConnectionsQuery
{
    public function __construct(private IdentityProviders $identityProviders) {}

    /**
     * The rider an account at a service belongs to, or null when nobody connected it.
     */
    public function riderFor(ConnectionProvider $connectionProvider, string $providerUserId): ?int
    {
        $userId = DB::table('connected_accounts')
            ->where('provider', $connectionProvider->value)
            ->where('provider_user_id', $providerUserId)
            ->value('user_id');

        return is_numeric($userId) ? (int) $userId : null;
    }

    /**
     * The service's number for the rider's connection there, or null when there is none.
     */
    public function identityOf(int $riderId, ConnectionProvider $connectionProvider): ?string
    {
        $id = DB::table('connected_accounts')
            ->where('user_id', $riderId)
            ->where('provider', $connectionProvider->value)
            ->value('provider_user_id');

        return is_string($id) ? $id : null;
    }

    public function emailTaken(string $email): bool
    {
        return DB::table('users')->where('email', mb_strtolower(trim($email)))->exists();
    }

    /**
     * The connected services the rider can log in with here, to confirm it is them without a password.
     *
     * @return list<array{slug: string, label: string}>
     */
    public function signInMethods(int $riderId): array
    {
        $methods = [];

        foreach ($this->connectedSlugs($riderId) as $slug) {
            $connectionProvider = ConnectionProvider::tryFrom($slug);

            if ($connectionProvider instanceof ConnectionProvider && $this->identityProviders->isEnabled($connectionProvider)) {
                $methods[] = ['slug' => $connectionProvider->value, 'label' => $connectionProvider->label()];
            }
        }

        return $methods;
    }

    public function page(int $riderId): ConnectionsPage
    {
        $rows = DB::table('connected_accounts')
            ->where('user_id', $riderId)
            ->get(['provider', 'account_label', 'needs_reconnect', 'connected_at', 'last_signed_in_at', 'last_sent_at'])
            ->keyBy('provider');

        $hasPassword = $this->hasPassword($riderId);
        $passkeys = $this->passkeys($riderId);
        $enabled = $this->identityProviders->enabled();
        $blocked = $this->blocked($rows->keys()->all(), $enabled, $hasPassword, $passkeys);

        $connected = [];
        $available = [];

        foreach ($this->ordered($enabled, $rows->keys()->all()) as $connectionProvider) {
            $row = $rows->get($connectionProvider->value);
            $reader = is_object($row) ? new RowReader($row) : null;
            $isEnabled = in_array($connectionProvider, $enabled, strict: true);

            $entry = [
                ...$this->describe($connectionProvider),
                'available' => $isEnabled,
                'connected' => $reader instanceof RowReader,
            ];

            if (! $reader instanceof RowReader) {
                $available[] = $entry;

                continue;
            }

            $disconnectBlocked = $blocked[$connectionProvider->value] ?? null;

            $connected[] = [
                ...$entry,
                'account_label' => $connectionProvider->keepsAccountLabel() ? $reader->optionalString('account_label') : null,
                'needs_reconnect' => (bool) $reader->int('needs_reconnect'),
                'connected_at' => $reader->iso('connected_at'),
                'last_signed_in_at' => $reader->iso('last_signed_in_at'),
                'last_sent_at' => $reader->iso('last_sent_at'),
                'manage_url' => $connectionProvider->manageUrl(),
                'withdraws_access' => $connectionProvider->keepsKeys(),
                'can_disconnect' => $disconnectBlocked === null,
                'disconnect_blocked' => $disconnectBlocked,
            ];
        }

        return new ConnectionsPage($connected, $available, $hasPassword, $passkeys > 0);
    }

    /**
     * Why the rider cannot disconnect each of these services, or null where they can, or never connected it.
     * Another page that offers Disconnect, such as Devices, says the same as the connections page.
     *
     * @param  list<ConnectionProvider>  $connectionProviders
     * @return array<string, string|null>
     */
    public function disconnectBlocked(int $riderId, array $connectionProviders): array
    {
        $connectedSlugs = DB::table('connected_accounts')->where('user_id', $riderId)->pluck('provider')->all();
        $blocked = $this->blocked($connectedSlugs, $this->identityProviders->enabled(), $this->hasPassword($riderId), $this->passkeys($riderId));
        $reasons = [];

        foreach ($connectionProviders as $connectionProvider) {
            $reasons[$connectionProvider->value] = $blocked[$connectionProvider->value] ?? null;
        }

        return $reasons;
    }

    /**
     * What every screen says about a service, whether or not the rider connected it.
     *
     * @return array{slug: string, label: string, group: string, group_label: string, purpose: string, note: string|null, keeps: list<string>, capabilities: list<array{key: string, label: string, later: bool}>}
     */
    public function describe(ConnectionProvider $connectionProvider): array
    {
        $capabilities = [];

        foreach ($connectionProvider->capabilities() as $connectionCapability) {
            $capabilities[] = ['key' => $connectionCapability->value, 'label' => $connectionCapability->label(), 'later' => false];
        }

        foreach ($connectionProvider->later() as $connectionCapability) {
            $capabilities[] = ['key' => $connectionCapability->value, 'label' => $connectionCapability->label(), 'later' => true];
        }

        return [
            'slug' => $connectionProvider->value,
            'label' => $connectionProvider->label(),
            'group' => $connectionProvider->group()->value,
            'group_label' => $connectionProvider->group()->label(),
            'purpose' => $connectionProvider->purpose(),
            'note' => $connectionProvider->note(),
            'keeps' => $connectionProvider->keeps(),
            'capabilities' => $capabilities,
        ];
    }

    /**
     * The connected services the rider cannot disconnect, with the reason they read. Without a password or a
     * passkey, the rider needs one other connection that still works here.
     *
     * @param  array<array-key, mixed>  $connectedSlugs
     * @param  list<ConnectionProvider>  $enabled
     * @return array<string, string>
     */
    private function blocked(array $connectedSlugs, array $enabled, bool $hasPassword, int $passkeys): array
    {
        if ($hasPassword || $passkeys > 0) {
            return [];
        }

        $connected = [];

        foreach ($connectedSlugs as $connectedSlug) {
            $connectionProvider = is_string($connectedSlug) ? ConnectionProvider::tryFrom($connectedSlug) : null;

            if ($connectionProvider instanceof ConnectionProvider) {
                $connected[] = $connectionProvider;
            }
        }

        $usable = count(array_filter($connected, static fn (ConnectionProvider $connectionProvider): bool => in_array($connectionProvider, $enabled, strict: true)));
        $blocked = [];

        foreach ($connected as $connectionProvider) {
            $others = $usable - (in_array($connectionProvider, $enabled, strict: true) ? 1 : 0);

            if ($others < 1) {
                $blocked[$connectionProvider->value] = ConnectionProblem::LastSignInMethod->message($connectionProvider);
            }
        }

        return $blocked;
    }

    private function hasPassword(int $riderId): bool
    {
        return DB::table('users')->where('id', $riderId)->whereNotNull('password')->exists();
    }

    private function passkeys(int $riderId): int
    {
        return DB::table('passkeys')->where('user_id', $riderId)->count();
    }

    /**
     * The services set up here, then any the rider connected that are no longer set up, so they can still
     * disconnect them.
     *
     * @param  list<ConnectionProvider>  $enabled
     * @param  array<array-key, mixed>  $connectedSlugs
     * @return list<ConnectionProvider>
     */
    private function ordered(array $enabled, array $connectedSlugs): array
    {
        $ordered = $enabled;

        foreach ($connectedSlugs as $connectedSlug) {
            $connectionProvider = is_string($connectedSlug) ? ConnectionProvider::tryFrom($connectedSlug) : null;

            if ($connectionProvider instanceof ConnectionProvider && ! in_array($connectionProvider, $ordered, strict: true)) {
                $ordered[] = $connectionProvider;
            }
        }

        return $ordered;
    }

    /**
     * The services the rider connected that know their account there. A Wahoo or Ride with GPS connection from
     * before has no number from the service yet, so it cannot vouch for the rider until they connect it again.
     *
     * @return list<string>
     */
    private function connectedSlugs(int $riderId): array
    {
        return array_values(array_filter(
            DB::table('connected_accounts')->where('user_id', $riderId)->whereNotNull('provider_user_id')->oldest('connected_at')->pluck('provider')->all(),
            is_string(...),
        ));
    }
}
