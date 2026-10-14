<?php

declare(strict_types=1);

use App\Domain\Releases\RiderNotes;

it('reads the first change as a one-line headline', function (): void {
    expect(RiderNotes::headline("## New\n\n* A **bigger** map, see [the planner](https://traceway.online)\n* Another"))
        ->toBe('A bigger map, see the planner')
        ->and(RiderNotes::headline(''))->toBeNull();
});

it('reads the words above the changes as the headline when there are some', function (): void {
    expect(RiderNotes::headline("Water, toilets, and cafés show up again.\n\n## Stops that load\n\n- **Water** loads again."))
        ->toBe('Water, toilets, and cafés show up again.');
});
