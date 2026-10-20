<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\ConnectionProvider;
use App\Contracts\Auth\IdentityProviders;
use App\Queries\RowReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Support\Aaguids;

final readonly class SecurityQuery
{
    public function __construct(private IdentityProviders $identityProviders) {}

    public function page(int $riderId): SecurityPage
    {
        $twoFactor = Features::canManageTwoFactorAuthentication();
        $passkeys = Features::canManagePasskeys() ? $this->passkeys($riderId) : [];
        $hasPassword = DB::table('users')->where('id', $riderId)->whereNotNull('password')->exists();

        return new SecurityPage(
            canManageTwoFactor: $twoFactor,
            canManagePasskeys: Features::canManagePasskeys(),
            passkeys: $passkeys,
            passwordRules: Password::defaults()->toPasswordRulesString(),
            hasPassword: $hasPassword,
            passkeyIsLastWayIn: ! $hasPassword && count($passkeys) === 1 && ! $this->logsInWithAService($riderId),
            twoFactorEnabled: $twoFactor ? $this->twoFactorEnabled($riderId) : null,
            requiresConfirmation: $twoFactor
                ? Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')
                : null,
        );
    }

    /**
     * True when a connection to a service that is set up here still logs the rider in. A Wahoo or Ride with GPS
     * connection from before has no number from the service yet, so it cannot.
     */
    private function logsInWithAService(int $riderId): bool
    {
        $slugs = DB::table('connected_accounts')
            ->where('user_id', $riderId)
            ->whereNotNull('provider_user_id')
            ->pluck('provider');

        foreach ($slugs as $slug) {
            $connectionProvider = is_string($slug) ? ConnectionProvider::tryFrom($slug) : null;

            if ($connectionProvider instanceof ConnectionProvider && $this->identityProviders->isEnabled($connectionProvider)) {
                return true;
            }
        }

        return false;
    }

    private function twoFactorEnabled(int $riderId): bool
    {
        $builder = DB::table('users')->where('id', $riderId)->whereNotNull('two_factor_secret');

        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')) {
            $builder->whereNotNull('two_factor_confirmed_at');
        }

        return $builder->exists();
    }

    /**
     * @return list<array{id: int, name: string, authenticator: string|null, created_at_diff: string, last_used_at_diff: string|null}>
     */
    private function passkeys(int $riderId): array
    {
        $rows = DB::table('passkeys')
            ->where('user_id', $riderId)
            ->latest()
            ->get(['id', 'name', 'credential', 'created_at', 'last_used_at']);

        $passkeys = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $createdAt = $reader->optionalString('created_at');
            $lastUsedAt = $reader->optionalString('last_used_at');
            $passkeys[] = [
                'id' => $reader->int('id'),
                'name' => $reader->string('name'),
                'authenticator' => $this->authenticator($reader->mixed('credential')),
                'created_at_diff' => $createdAt === null || $createdAt === ''
                    ? ''
                    : CarbonImmutable::parse($createdAt)->diffForHumans(),
                'last_used_at_diff' => $lastUsedAt === null || $lastUsedAt === ''
                    ? null
                    : CarbonImmutable::parse($lastUsedAt)->diffForHumans(),
            ];
        }

        return $passkeys;
    }

    private function authenticator(mixed $credential): ?string
    {
        if (is_string($credential)) {
            $decoded = json_decode($credential, associative: true);
            $credential = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($credential)) {
            return null;
        }

        $aaguid = $credential['aaguid'] ?? null;

        if (! is_string($aaguid) || $aaguid === Aaguids::unknown()) {
            return null;
        }

        return Aaguids::labelFor($aaguid);
    }
}
