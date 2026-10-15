<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RouteServiceAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * A rider's connected Wahoo account with a token that is still good.
 *
 * @extends Factory<RouteServiceAccount>
 */
class RouteServiceAccountFactory extends Factory
{
    #[Override]
    protected $model = RouteServiceAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service' => 'wahoo',
            'access_token' => Str::random(40),
            'refresh_token' => Str::random(40),
            'expires_at' => now()->addHours(2),
            'needs_reconnect' => false,
            'connected_at' => now(),
        ];
    }
}
