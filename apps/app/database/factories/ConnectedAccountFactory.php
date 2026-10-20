<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * A rider's connected Wahoo account with a token that is still good. Other services keep no keys, so use
 * signInOnly() for them.
 *
 * @extends Factory<ConnectedAccount>
 */
class ConnectedAccountFactory extends Factory
{
    #[Override]
    protected $model = ConnectedAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'wahoo',
            'provider_user_id' => (string) fake()->unique()->numberBetween(1000, 9_999_999),
            'account_label' => null,
            'access_token' => Str::random(40),
            'refresh_token' => Str::random(40),
            'expires_at' => now()->addHours(2),
            'scopes' => null,
            'needs_reconnect' => false,
            'connected_at' => now(),
        ];
    }

    /**
     * A connection that only logs the rider in, such as Google, with no keys kept.
     */
    public function signInOnly(string $provider = 'google'): static
    {
        return $this->state(fn (): array => [
            'provider' => $provider,
            'account_label' => fake()->safeEmail(),
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ]);
    }

    /**
     * A Wahoo or Ride with GPS row carried over from before connections had the service's number.
     */
    public function withoutIdentity(): static
    {
        return $this->state(fn (): array => ['provider_user_id' => null]);
    }
}
