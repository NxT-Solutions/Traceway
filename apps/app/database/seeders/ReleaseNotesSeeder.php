<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Release;
use Illuminate\Database\Seeder;

/**
 * Two releases, so the bell and the What's new page have something to show before
 * releases:sync has read the real ones from GitHub.
 */
class ReleaseNotesSeeder extends Seeder
{
    public function run(): void
    {
        Release::query()->updateOrCreate(['tag' => 'v0.2.0'], [
            'name' => 'Stops along the way',
            'notes' => "## New\n\n* Water, toilets, and cafés along your route\n* A privacy policy, terms, and a legal notice\n\n## Fixes\n\n* Saved routes open faster on a phone",
            'published_at' => now()->subDay(),
        ]);

        Release::query()->updateOrCreate(['tag' => 'v0.1.0'], [
            'name' => 'First release',
            'notes' => "## New\n\n* Plan a loop for any sport\n* Send a route to your Garmin\n\n## Devices\n\n* The Traceway app for Garmin watches and bike computers",
            'published_at' => now()->subWeek(),
        ]);
    }
}
