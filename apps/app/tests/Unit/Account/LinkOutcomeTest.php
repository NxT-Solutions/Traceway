<?php

declare(strict_types=1);

use App\Account\LinkOutcome;

covers(LinkOutcome::class);

uses()->group('mutation');

it('says when a connection adds a way to log in, and when it lets go of the keys it held', function (LinkOutcome $linkOutcome, bool $addsSignIn, bool $supersedesKeys): void {
    expect($linkOutcome->addsSignIn())->toBe($addsSignIn)
        ->and($linkOutcome->supersedesKeys())->toBe($supersedesKeys);
})->with([
    'a new connection' => [LinkOutcome::Linked, true, false],
    'the same account again' => [LinkOutcome::Refreshed, false, true],
    'a connection from before that gets its number' => [LinkOutcome::Claimed, true, true],
    'another account in its place' => [LinkOutcome::Replaced, true, false],
]);
