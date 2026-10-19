<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\Email;
use App\Account\MissingRider;
use App\Account\Rider;
use App\Account\RiderId;
use App\Account\RiderName;
use App\Account\Role;
use App\Contracts\Account\UserRepository;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
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

    public function role(RiderId $riderId): Role
    {
        return Role::strongest($this->model($riderId)->getRoleNames());
    }

    public function assignRole(RiderId $riderId, Role $role): void
    {
        $this->model($riderId)->syncRoles([$role->value]);
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
            $user->forceFill(['email_verified_at' => now()])->save();
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
        // The query builder, so the rider's updated_at stays the last time they changed their account.
        DB::table('users')->where('id', $riderId->value)->update(['last_seen_at' => CarbonImmutable::instance($seenAt)]);
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

            $user->name = $riderName->value;
            $user->email = $email->value;
            $user->save();
        });
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
            $builder = DB::table('oauth_access_tokens')->where('user_id', $user->id);

            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', (clone $builder)->select('id'))->delete();
            $builder->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->id)->delete();

            $user->delete();
        });

        return $rider;
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
