<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<SavedRoute>
 */
class SavedRouteFactory extends Factory
{
    #[Override]
    protected $model = SavedRoute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Morning loop',
            'sport' => 'road',
            'surface' => 'paved',
            'elevation' => 'neutral',
            'roads' => 'quiet',
            'distance_metres' => 20_000,
            'ascent_metres' => 120,
            'coordinates' => [
                [3.72, 51.05],
                [3.74, 51.06],
                [3.72, 51.05],
            ],
        ];
    }
}
