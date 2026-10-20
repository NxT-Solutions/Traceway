<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\Email;
use App\Account\LastSuperAdmin;
use App\Account\MissingRider;
use App\Account\Rider;
use App\Account\RiderId;
use App\Account\RiderName;
use App\Account\Role;
use App\Contracts\Account\UserRepository;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

final readonly class EloquentUserRepository implements UserRepository
{
    public function create(RiderName $riderName, Email $email, #[SensitiveParameter] string $password): Rider
    {
        $user = DB::transaction(function () use ($riderName, $email, $password): User {
            $user = User::query()->create([
                'name' => $riderName->value,
                'email' => $email->value,
                'password' => $password,
            ]);
            $user->assignRole(Role::User->value);

            return $user;
        });

        return $this->rider($user);
    }

    public function createConfirmed(RiderName $riderName, Email $email, #[SensitiveParameter] string $password): Rider
    {
        $user = DB::transaction(function () use ($riderName, $email, $password): User {
            $user = User::query()->create([
                'name' => $riderName->value,
                'email' => $email->value,
                'password' => $password,
            ]);
            $user->forceFill(['email_verified_at' => now(), 'welcomed_at' => now()])->save();
            $user->assignRole(Role::User->value);

            return $user;
        });

        return $this->rider($user);
    }

    public function createWithoutPassword(RiderName $riderName, Email $email, bool $emailConfirmed): Rider
    {
        $user = DB::transaction(function () use ($riderName, $email, $emailConfirmed): User {
            $user = User::query()->create([
                'name' => $riderName->value,
                'email' => $email->value,
                'password' => null,
            ]);

            if ($emailConfirmed) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->assignRole(Role::User->value);

            return $user;
        });

        return $this->rider($user);
    }

    public function findByEmail(Email $email): ?Rider
    {
        $user = User::query()->where('email', $email->value)->first();

        return $user instanceof User ? $this->rider($user) : null;
    }

    public function hasPassword(RiderId $riderId): bool
    {
        return DB::table('users')->where('id', $riderId->value)->whereNotNull('password')->exists();
    }

    public function passkeyCount(RiderId $riderId): int
    {
        return DB::table('passkeys')->where('user_id', $riderId->value)->count();
    }

    public function deletePasskeys(RiderId $riderId): void
    {
        DB::table('passkeys')->where('user_id', $riderId->value)->delete();
    }

    public function role(RiderId $riderId): Role
    {
        return Role::strongest($this->model($riderId)->getRoleNames());
    }

    public function assignRole(RiderId $riderId, Role $role): void
    {
        // One transaction, which SQLite takes for writing at once, so two Super Admins demoting each other at the
        // same moment run one after the other and the second finds no Super Admin left.
        DB::transaction(function () use ($riderId, $role): void {
            $user = $this->model($riderId);
            $before = Role::strongest($user->getRoleNames());

            $user->syncRoles([$role->value]);

            $this->assertASuperAdminRemains($before);
        });
    }

    public function countWithRole(Role $role): int
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $role->value)
            ->where('model_has_roles.model_type', new User()->getMorphClass())
            ->count();
    }

    public function confirmEmail(RiderId $riderId): void
    {
        $user = $this->model($riderId);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now(), 'welcomed_at' => $user->welcomed_at ?? now()])->save();
        }
    }

    public function resetTwoFactor(RiderId $riderId): void
    {
        $this->model($riderId)->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function endSessions(RiderId $riderId): void
    {
        $user = $this->model($riderId);

        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->forceFill(['remember_token' => Str::random(60)])->save();
        });
    }

    public function seen(RiderId $riderId, DateTimeImmutable $seenAt): void
    {
        // Kept to the quarter hour, as the privacy policy says, and written only when that quarter is new, so a
        // watch or an assistant asking often writes the account once a quarter hour at most. The query builder
        // keeps the rider's updated_at the last time they changed their account.
        $at = CarbonImmutable::instance($seenAt);
        $quarter = $at->setTimestamp($at->getTimestamp() - $at->getTimestamp() % 900);

        DB::table('users')
            ->where('id', $riderId->value)
            ->where(fn (Builder $builder): Builder => $builder->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $quarter))
            ->update(['last_seen_at' => $quarter]);
    }

    public function find(RiderId $riderId): Rider
    {
        return $this->rider($this->model($riderId));
    }

    public function updateProfile(RiderId $riderId, RiderName $riderName, Email $email, bool $clearEmailVerification): void
    {
        $user = $this->model($riderId);

        DB::transaction(function () use ($user, $riderName, $email, $clearEmailVerification): void {
            if ($clearEmailVerification) {
                $user->email_verified_at = null;
            }

            if ($user->email !== $email->value) {
                $user->pending_email = null;
            }

            $user->name = $riderName->value;
            $user->email = $email->value;
            $user->save();
        });
    }

    public function requestEmailChange(RiderId $riderId, Email $email): void
    {
        $this->model($riderId)->forceFill(['pending_email' => $email->value])->save();
    }

    public function pendingEmail(RiderId $riderId): ?Email
    {
        $pending = $this->model($riderId)->pending_email;

        return is_string($pending) && $pending !== '' ? new Email($pending) : null;
    }

    public function confirmEmailChange(RiderId $riderId, Email $email): ?Email
    {
        return DB::transaction(function () use ($riderId, $email): ?Email {
            $user = $this->model($riderId);

            if ($user->pending_email !== $email->value) {
                return null;
            }

            $previous = new Email($user->email);

            $user->forceFill([
                'email' => $email->value,
                'email_verified_at' => now(),
                'welcomed_at' => $user->welcomed_at ?? now(),
                'pending_email' => null,
            ])->save();

            return $previous;
        });
    }

    public function cancelEmailChange(RiderId $riderId): void
    {
        $this->model($riderId)->forceFill(['pending_email' => null])->save();
    }

    public function markWelcomed(RiderId $riderId): ?Rider
    {
        $user = $this->model($riderId);

        $marked = User::query()
            ->whereKey($user->id)
            ->where('welcomed_at', '=', value: null)
            ->update(['welcomed_at' => now()]);

        return $marked === 1 ? $this->rider($user) : null;
    }

    public function changePassword(RiderId $riderId, #[SensitiveParameter] string $password): Rider
    {
        $user = $this->model($riderId);

        DB::transaction(function () use ($user, $password): void {
            $user->password = $password;
            $user->save();
        });

        return $this->rider($user);
    }

    public function delete(RiderId $riderId): Rider
    {
        $user = $this->model($riderId);
        $rider = $this->rider($user);

        DB::transaction(function () use ($user): void {
            $role = Role::strongest($user->getRoleNames());
            $builder = DB::table('oauth_access_tokens')->where('user_id', $user->id);

            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', (clone $builder)->select('id'))->delete();
            $builder->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->id)->delete();

            $user->delete();

            $this->assertASuperAdminRemains($role);
        });

        return $rider;
    }

    /**
     * Undoes the write when it took away the last Super Admin.
     */
    private function assertASuperAdminRemains(Role $role): void
    {
        if ($role === Role::SuperAdmin && $this->countWithRole(Role::SuperAdmin) === 0) {
            throw LastSuperAdmin::wouldRemain();
        }
    }

    private function model(RiderId $riderId): User
    {
        $user = User::query()->find($riderId->value);

        if (! $user instanceof User) {
            throw MissingRider::id($riderId->value);
        }

        return $user;
    }

    private function rider(User $user): Rider
    {
        return new Rider(new RiderId($user->id), new RiderName($user->name), new Email($user->email));
    }
}
