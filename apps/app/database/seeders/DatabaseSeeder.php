<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Commands\SyncReleasesCommand;
use App\Contracts\Bus\DispatchesCommands;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(DemoRiderSeeder::class);
        // The rider notes in resources/releases, so the bell has something to show.
        resolve(DispatchesCommands::class)->dispatch(new SyncReleasesCommand);
    }
}
