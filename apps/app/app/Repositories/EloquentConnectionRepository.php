<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\ConnectedIdentity;
use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Account\LinkOutcome;
use App\Account\RiderId;
use App\Contracts\Account\ConnectionRepository;
use App\Models\ConnectedAccount;
use App\Routes\RouteId;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class EloquentConnectionRepository implements ConnectionRepository
{
    public function riderFor(ConnectionProvider $connectionProvider, string $providerUserId): ?RiderId
    {
        $userId = ConnectedAccount::query()
            ->where('provider', $connectionProvider->value)
            ->where('provider_user_id', $providerUserId)
            ->value('user_id');

        return is_int($userId) ? new RiderId($userId) : null;
    }

    public function identityOf(RiderId $riderId, ConnectionProvider $connectionProvider): ?string
    {
        return $this->account($riderId, $connectionProvider)?->provider_user_id;
    }

    public function link(RiderId $riderId, ConnectedIdentity $connectedIdentity): LinkOutcome
    {
        return DB::transaction(function () use ($riderId, $connectedIdentity): LinkOutcome {
            $account = $this->account($riderId, $connectedIdentity->provider);

            $outcome = match (true) {
                ! $account instanceof ConnectedAccount => LinkOutcome::Linked,
                $account->provider_user_id === null => LinkOutcome::Claimed,
                $account->provider_user_id === $connectedIdentity->providerUserId => LinkOutcome::Refreshed,
                default => LinkOutcome::Replaced,
            };

            $account ??= new ConnectedAccount([
                'user_id' => $riderId->value,
                'provider' => $connectedIdentity->provider->value,
            ]);

            $account->fill([
                'provider_user_id' => $connectedIdentity->providerUserId,
                'account_label' => $connectedIdentity->accountLabel,
                'scopes' => $connectedIdentity->scopes === [] ? null : $connectedIdentity->scopes,
                'needs_reconnect' => false,
            ]);

            if ($outcome === LinkOutcome::Linked || $outcome === LinkOutcome::Replaced) {
                $account->fill(['connected_at' => Date::now()]);
            }

            $tokens = $connectedIdentity->tokens;

            if ($tokens instanceof ConnectionTokens) {
                $account->fill($this->tokenColumns($tokens));
            } elseif ($outcome === LinkOutcome::Replaced || ! $connectedIdentity->provider->keepsKeys()) {
                $account->fill(['access_token' => null, 'refresh_token' => null, 'expires_at' => null]);
            }

            $account->save();

            if ($outcome === LinkOutcome::Replaced) {
                // Routes sent to the old account are not in the new one's library.
                DB::table('sent_routes')
                    ->where('user_id', $riderId->value)
                    ->where('service', $connectedIdentity->provider->value)
                    ->delete();
            }

            return $outcome;
        });
    }

    /**
     * @return list<RiderId>
     */
    public function unidentifiedWithLiveKeys(ConnectionProvider $connectionProvider): array
    {
        return $this->riders(DB::table('connected_accounts')
            ->where('provider', $connectionProvider->value)
            ->whereNull('provider_user_id')
            ->whereNotNull('access_token')
            ->where('needs_reconnect', operator: false));
    }

    public function identify(RiderId $riderId, ConnectionProvider $connectionProvider, string $providerUserId, ?string $accountLabel): void
    {
        $account = $this->account($riderId, $connectionProvider);

        if (! $account instanceof ConnectedAccount || $account->provider_user_id !== null) {
            return;
        }

        $account->fill(['provider_user_id' => $providerUserId]);

        if ($accountLabel !== null && $connectionProvider->keepsAccountLabel()) {
            $account->fill(['account_label' => $accountLabel]);
        }

        $account->save();
    }

    /**
     * @return list<RiderId>
     */
    public function withKeys(ConnectionProvider $connectionProvider): array
    {
        return $this->riders(DB::table('connected_accounts')
            ->where('provider', $connectionProvider->value)
            ->whereNotNull('access_token'));
    }

    public function signedIn(RiderId $riderId, ConnectionProvider $connectionProvider, DateTimeImmutable $signedInAt): void
    {
        $this->account($riderId, $connectionProvider)?->fill(['last_signed_in_at' => $signedInAt])->save();
    }

    public function unlink(RiderId $riderId, ConnectionProvider $connectionProvider): void
    {
        DB::transaction(function () use ($riderId, $connectionProvider): void {
            ConnectedAccount::query()
                ->where('user_id', $riderId->value)
                ->where('provider', $connectionProvider->value)
                ->delete();

            DB::table('sent_routes')
                ->where('user_id', $riderId->value)
                ->where('service', $connectionProvider->value)
                ->delete();
        });
    }

    /**
     * @return list<ConnectionProvider>
     */
    public function connected(RiderId $riderId): array
    {
        $providers = [];

        foreach (ConnectedAccount::query()->where('user_id', $riderId->value)->oldest('connected_at')->pluck('provider') as $slug) {
            $connectionProvider = is_string($slug) ? ConnectionProvider::tryFrom($slug) : null;

            if ($connectionProvider instanceof ConnectionProvider) {
                $providers[] = $connectionProvider;
            }
        }

        return $providers;
    }

    public function tokens(RiderId $riderId, ConnectionProvider $connectionProvider): ?ConnectionTokens
    {
        $account = $this->account($riderId, $connectionProvider);

        if (! $account instanceof ConnectedAccount || $account->access_token === null || $account->access_token === '') {
            return null;
        }

        return new ConnectionTokens(
            $account->access_token,
            $account->refresh_token,
            $account->expires_at?->getTimestamp(),
        );
    }

    public function refreshed(RiderId $riderId, ConnectionProvider $connectionProvider, ConnectionTokens $connectionTokens): void
    {
        $this->account($riderId, $connectionProvider)?->fill([
            ...$this->tokenColumns($connectionTokens),
            'needs_reconnect' => false,
        ])->save();
    }

    public function needsReconnect(RiderId $riderId, ConnectionProvider $connectionProvider): void
    {
        $this->account($riderId, $connectionProvider)?->fill(['needs_reconnect' => true])->save();
    }

    public function forgetKeys(RiderId $riderId, ConnectionProvider $connectionProvider): void
    {
        $this->account($riderId, $connectionProvider)?->fill([
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'needs_reconnect' => true,
        ])->save();
    }

    public function sentId(RiderId $riderId, RouteId $routeId, int $leg, ConnectionProvider $connectionProvider): ?string
    {
        $id = DB::table('sent_routes')
            ->where('user_id', $riderId->value)
            ->where('saved_route_id', $routeId->value)
            ->where('leg', $leg)
            ->where('service', $connectionProvider->value)
            ->value('external_id');

        return is_string($id) ? $id : null;
    }

    public function recordSent(RiderId $riderId, RouteId $routeId, int $leg, ConnectionProvider $connectionProvider, ?string $externalId, ?string $externalUrl): void
    {
        DB::transaction(function () use ($riderId, $routeId, $leg, $connectionProvider, $externalId, $externalUrl): void {
            DB::table('sent_routes')->updateOrInsert(
                ['saved_route_id' => $routeId->value, 'leg' => $leg, 'service' => $connectionProvider->value],
                ['user_id' => $riderId->value, 'external_id' => $externalId, 'external_url' => $externalUrl, 'sent_at' => now()],
            );

            $this->account($riderId, $connectionProvider)?->fill(['last_sent_at' => now()])->save();
        });
    }

    /**
     * The riders the matching connections belong to, oldest connection first.
     *
     * @return list<RiderId>
     */
    private function riders(Builder $builder): array
    {
        $riders = [];

        foreach ($builder->oldest('connected_at')->pluck('user_id') as $userId) {
            if (is_numeric($userId)) {
                $riders[] = new RiderId((int) $userId);
            }
        }

        return $riders;
    }

    private function account(RiderId $riderId, ConnectionProvider $connectionProvider): ?ConnectedAccount
    {
        return ConnectedAccount::query()
            ->where('user_id', $riderId->value)
            ->where('provider', $connectionProvider->value)
            ->first();
    }

    /**
     * @return array{access_token: string, refresh_token: string|null, expires_at: CarbonInterface|null}
     */
    private function tokenColumns(ConnectionTokens $connectionTokens): array
    {
        return [
            'access_token' => $connectionTokens->accessToken,
            'refresh_token' => $connectionTokens->refreshToken,
            'expires_at' => $connectionTokens->expiresAt === null ? null : Date::createFromTimestamp($connectionTokens->expiresAt),
        ];
    }
}
