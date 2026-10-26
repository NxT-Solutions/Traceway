<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AvoidedStretch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<AvoidedStretch>
 */
class AvoidedStretchFactory extends Factory
{
    #[Override]
    protected $model = AvoidedStretch::class;

    /**
     * About 280 m of an east-west road in Ghent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'latitude' => 51.05,
            'longitude' => 3.722,
            'way_id' => 4_242,
            'name' => 'Bosweg',
            'sport' => 'gravel',
            'surface' => 'mixed',
            'shape' => [
                [3.72, 51.05],
                [3.724, 51.05],
            ],
        ];
    }
}
