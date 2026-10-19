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

        /** @var array<string, array{name: string, email: string, description: string, role?: string}> $riders */
        $riders = config('demo.riders', []);

        $password = config('demo.password');

        foreach ($riders as $rider) {
            $user = User::query()->updateOrCreate(
                ['email' => $rider['email']],
                [
                    'name' => $rider['name'],
                    'password' => Hash::make(is_string($password) ? $password : 'password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([(Role::tryFrom($rider['role'] ?? '') ?? Role::User)->value]);
        }
    }
}
