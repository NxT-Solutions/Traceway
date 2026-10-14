<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * A release published a day ago, with one new thing in it.
 *
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
{
    #[Override]
    protected $model = Release::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tag = 'v1.'.fake()->unique()->numberBetween(0, 999).'.0';

        return [
            'tag' => $tag,
            'name' => 'Faster near the coast',
            'summary' => null,
            'notes' => "## New\n\n* Routes plan faster near the coast",
            'published_at' => now()->subDay(),
        ];
    }
}
