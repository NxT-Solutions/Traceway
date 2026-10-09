<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Queries\RowReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Support\Aaguids;

final class SecurityQuery
{
    public function page(int $riderId): SecurityPage
    {
        $twoFactor = Features::canManageTwoFactorAuthentication();

        return new SecurityPage(
            canManageTwoFactor: $twoFactor,
            canManagePasskeys: Features::canManagePasskeys(),
            passkeys: Features::canManagePasskeys() ? $this->passkeys($riderId) : [],
            passwordRules: Password::defaults()->toPasswordRulesString(),
            twoFactorEnabled: $twoFactor ? $this->twoFactorEnabled($riderId) : null,
            requiresConfirmation: $twoFactor
                ? Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')
                : null,
        );
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
