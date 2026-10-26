<?php

declare(strict_types=1);

use App\Domain\Routing\AvoidCheck;
use App\Domain\Routing\AvoidedStretch;

/** About 280 m of an east-west road. */
function bosweg(string $id = 'bosweg'): AvoidedStretch
{
    return new AvoidedStretch($id, 51.05, 3.722, [[3.72, 51.05], [3.724, 51.05]], 'Bosweg');
}

it('finds a line that rides along an avoided stretch', function (): void {
    expect(AvoidCheck::crossing([[3.715, 51.05], [3.73, 51.05]], [bosweg()]))->toBe(['bosweg']);
});

it('finds it when the line rides it the other way', function (): void {
    expect(AvoidCheck::crossing([[3.73, 51.05001], [3.715, 51.05001]], [bosweg()]))->toBe(['bosweg']);
});

it('leaves a road that only crosses the stretch at a junction', function (): void {
    expect(AvoidCheck::crossing([[3.722, 51.045], [3.722, 51.055]], [bosweg()]))->toBe([]);
});

it('leaves a road 20 m beside the stretch', function (): void {
    expect(AvoidCheck::crossing([[3.715, 51.05018], [3.73, 51.05018]], [bosweg()]))->toBe([]);
});

it('leaves a cycle track mapped 6 m beside the stretch', function (): void {
    expect(AvoidCheck::crossing([[3.715, 51.050054], [3.73, 51.050054]], [bosweg()]))->toBe([]);
});

it('leaves the road a short stretch meets end on', function (): void {
    $short = new AvoidedStretch('short', 51.05, 3.72012, [[3.72, 51.05], [3.72023, 51.05]]);

    expect(AvoidCheck::crossing([[3.71, 51.05], [3.72, 51.05], [3.72, 51.06]], [$short]))->toBe([]);
});

it('leaves a line that only touches the end of the stretch', function (): void {
    expect(AvoidCheck::crossing([[3.71, 51.05], [3.7202, 51.05], [3.7202, 51.06]], [bosweg()]))->toBe([]);
});

it('counts a short stretch ridden end to end', function (): void {
    $short = new AvoidedStretch('short', 51.05, 3.72015, [[3.72, 51.05], [3.7203, 51.05]]);

    expect(AvoidCheck::crossing([[3.7195, 51.05], [3.7205, 51.05]], [$short]))->toBe(['short']);
});

it('names only the stretches the line rides', function (): void {
    $elsewhere = new AvoidedStretch('elsewhere', 51.1, 3.8, [[3.79, 51.1], [3.81, 51.1]]);

    expect(AvoidCheck::crossing([[3.715, 51.05], [3.73, 51.05]], [$elsewhere, bosweg()]))->toBe(['bosweg']);
});

it('finds nothing without avoided stretches or without a line', function (): void {
    expect(AvoidCheck::crossing([[3.715, 51.05], [3.73, 51.05]], []))->toBe([])
        ->and(AvoidCheck::crossing([[3.715, 51.05]], [bosweg()]))->toBe([]);
});
