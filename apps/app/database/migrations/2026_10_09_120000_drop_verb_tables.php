<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('verb_state_events');
        Schema::dropIfExists('verb_snapshots');
        Schema::dropIfExists('verb_events');
    }

    public function down(): void
    {
        // The event store left with the package. There is nothing to restore.
    }
};
