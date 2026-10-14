<?php

declare(strict_types=1);

use App\Domain\Releases\RiderNotes;

const GENERATED_NOTES = <<<'MD'
<!-- Release notes generated using configuration in .github/release.yml at main -->

## What's Changed
### New
* ✨ feat: a privacy policy, terms, and a legal notice by @NoahNxT in https://github.com/NxT-Solutions/Traceway/pull/33
* :sparkles: feat(planner): plan a loop from a town name (#31) by @NoahNxT in https://github.com/NxT-Solutions/Traceway/pull/31
### Fixes
* 🐛 fix: water, toilets, and cafés come from the map tiles by @NoahNxT in https://github.com/NxT-Solutions/Traceway/pull/34
### Docs
* 📝 docs: a shorter README by @NoahNxT in https://github.com/NxT-Solutions/Traceway/pull/30
### Dependencies
* Bump vite from 7.0.0 to 7.1.0 by @dependabot[bot] in https://github.com/NxT-Solutions/Traceway/pull/29
### Other changes
* 🧪 test: the screens run saves the route it draws by @NoahNxT in https://github.com/NxT-Solutions/Traceway/pull/28

## New Contributors
* @someone made their first contribution in https://github.com/NxT-Solutions/Traceway/pull/27

**Full Changelog**: https://github.com/NxT-Solutions/Traceway/compare/v0.1.0...v0.2.0
MD;

it('keeps what is new and what was fixed, as plain sentences', function (): void {
    expect(RiderNotes::from(GENERATED_NOTES))->toBe(<<<'MD'
        ## New

        * A privacy policy, terms, and a legal notice
        * Plan a loop from a town name

        ## Fixes

        * Water, toilets, and cafés come from the map tiles
        MD);
});

it('leaves out docs, dependencies, other changes, contributors, and the changelog link', function (): void {
    expect(RiderNotes::from(GENERATED_NOTES))
        ->not->toContain('README')
        ->not->toContain('vite')
        ->not->toContain('screens run')
        ->not->toContain('first contribution')
        ->not->toContain('Full Changelog')
        ->not->toContain('github.com')
        ->not->toContain('<!--');
});

it('keeps device changes', function (): void {
    expect(RiderNotes::from("## What's Changed\n### Devices\n* 📱 feat: the Garmin app shows the next turn by @a in https://github.com/x/y/pull/1"))
        ->toBe("## Devices\n\n* The Garmin app shows the next turn");
});

it('has nothing for a rider when a release only changed docs and tests', function (): void {
    expect(RiderNotes::from("## What's Changed\n### Docs\n* docs: typo by @a in https://github.com/x/y/pull/1\n### Other changes\n* test: more by @a in https://github.com/x/y/pull/2"))
        ->toBe('');
});

it('keeps notes written by hand whole', function (): void {
    expect(RiderNotes::from("Routes now avoid busy roads.\n\n- Faster loops\n- Clearer turns"))
        ->toBe("Routes now avoid busy roads.\n\n- Faster loops\n- Clearer turns");
});

it('reads the first change as a one-line headline', function (): void {
    expect(RiderNotes::headline("## New\n\n* A **bigger** map, see [the planner](https://traceway.online)\n* Another"))
        ->toBe('A bigger map, see the planner')
        ->and(RiderNotes::headline('Only a sentence.'))->toBeNull();
});

it('keeps words written above the groups, and headings of its own', function (): void {
    expect(RiderNotes::from("Maps load twice as fast now.\r\n\r\n## What's Changed\r\n### New\r\n* feat!: stops along the way by @a in https://github.com/x/y/pull/1\r\n### Other changes\r\n* ci: faster by @a in https://github.com/x/y/pull/2\r\n## Known issues\r\n* Polar is not there yet"))
        ->toBe("Maps load twice as fast now.\n\n## New\n\n* Stops along the way\n\n## Known issues\n\n* Polar is not there yet");
});

it("keeps a plain list under What's changed", function (): void {
    expect(RiderNotes::from("## What's changed\n\n* :zap: perf(map): faster maps"))
        ->toBe('* Faster maps');
});
