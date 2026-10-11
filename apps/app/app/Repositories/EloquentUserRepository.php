<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\Email;
use App\Account\MissingRider;
use App\Account\Rider;
use App\Account\RiderId;
use App\Account\RiderName;
use App\Contracts\Account\UserRepository;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

final readonly class EloquentUserRepository implements UserRepository
{
    public function create(RiderName $riderName, Email $email, #[SensitiveParameter] string $password): Rider
    {
        $user = DB::transaction(fn (): User => User::query()->create([
            'name' => $riderName->value,
            'email' => $email->value,
            'password' => $password,
        ]));

        return $this->rider($user);
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
