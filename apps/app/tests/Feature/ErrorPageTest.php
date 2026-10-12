<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('shows the branded page for a missing planner page', function (): void {
    $this->get('/saved/does-not-exist/at-all')
        ->assertNotFound()
        ->assertSee('Off the map')
        ->assertSee('Back to the start');
});

it('shows the branded page for a share link with a bad signature', function (): void {
    $this->get('/share/00000000-0000-0000-0000-000000000000')
        ->assertForbidden()
        ->assertSee('Private trail');
});

it('hides the cause of a server error behind the branded page', function (): void {
    config(['app.debug' => false]);
    Route::get('/_test/boom', fn () => throw new RuntimeException('database password is hunter2'));

    $this->get('/_test/boom')
        ->assertInternalServerError()
        ->assertSee('We hit a pothole')
        ->assertSee('Try again')
        ->assertDontSee('hunter2');
});

it('falls back by status class for codes without their own words', function (int $status, string $title): void {
    Route::get('/_test/abort', fn () => abort($status));

    $this->get('/_test/abort')
        ->assertStatus($status)
        ->assertSee($title);
})->with([
    'a known client error' => [413, 'That is a heavy load'],
    'an unknown client error' => [418, 'That did not work'],
    'a known server error' => [504, 'Still climbing'],
    'an unknown server error' => [507, 'We hit a pothole'],
]);
