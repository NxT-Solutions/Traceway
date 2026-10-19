<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Account\Role;
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

        /** @var array<string, array{name: string, email: string, description: string}> $riders */
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

            // The road rider looks after the others, so the admin area can be tried without a Super Admin.
            $user->syncRoles([($key === 'road' ? Role::Admin : Role::User)->value]);
        }
    }
}
