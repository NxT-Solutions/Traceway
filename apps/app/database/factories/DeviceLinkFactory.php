<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Account\PairingCode;
use App\Models\DeviceLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * A pairing that waits for a rider to type its code.
 *
 * @extends Factory<DeviceLink>
 */
class DeviceLinkFactory extends Factory
{
    #[Override]
    protected $model = DeviceLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pairing_code' => PairingCode::random()->value,
            'pairing_secret_hash' => hash('sha256', Str::random(48)),
            'pairing_expires_at' => now()->addMinutes(10),
        ];
    }
}
