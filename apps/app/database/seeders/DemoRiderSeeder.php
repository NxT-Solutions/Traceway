<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Account\ConnectionProvider;
use App\Account\Role;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoRiderSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true) {
            return;
        }

        /** @var array<string, array{name: string, email: string, description: string, role?: string}> $riders */
        $riders = config('demo.riders', []);

        $password = config('demo.password');

        foreach ($riders as $key => $rider) {
            $user = User::query()->updateOrCreate(
                ['email' => $rider['email']],
                [
                    'name' => $rider['name'],
                    'password' => Hash::make(is_string($password) ? $password : 'password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([(Role::tryFrom($rider['role'] ?? '') ?? Role::User)->value]);

            if ($key === 'road') {
                $this->connect($user, $rider['email']);
            }
        }
    }

    /**
     * Road logs in with Google and Strava too, so Settings, then Connections shows connected accounts. Neither
     * keeps keys, and Strava shares no email address.
     */
    private function connect(User $user, string $email): void
    {
        $connections = [
            ConnectionProvider::Google->value => $email,
            ConnectionProvider::Strava->value => null,
        ];

        foreach ($connections as $provider => $label) {
            ConnectedAccount::query()->updateOrCreate(
                ['user_id' => $user->id, 'provider' => $provider],
                [
                    'provider_user_id' => 'demo-road-'.$provider,
                    'account_label' => $label,
                    'connected_at' => now(),
                ],
            );
        }
    }
}
