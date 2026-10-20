<?php

declare(strict_types=1);

use App\Account\ConnectionProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The operator notes and the privacy policy describe connections. These keep them true when the code moves.
 */
function connectionsGuideText(string $path): string
{
    return (string) file_get_contents(dirname(base_path(), 2).'/'.$path);
}

/**
 * The services a sentence of the privacy policy names, such as "For Strava, Garmin Connect, and Runkeeper, it".
 *
 * @return list<string>
 */
function connectionsGuideNames(string $sentence): array
{
    return array_values(array_filter(array_map(
        fn (string $name): string => trim((string) preg_replace('/^and /', '', trim($name))),
        explode(',', $sentence),
    )));
}

it('tells the operator to register the sender with Apple before turning Apple on', function (): void {
    expect(connectionsGuideText('apps/app/.env.example'))->toContain('Sign in with Apple for Email Communication')
        ->and(connectionsGuideText('docs/device-providers.md'))->toContain('Sign in with Apple for Email Communication');
});

it('documents connection commands that exist', function (): void {
    preg_match_all('/php artisan (connections:[a-z-]+)/', connectionsGuideText('docs/device-providers.md'), $matches);

    expect($matches[1])->toContain('connections:identify', 'connections:keep-access')
        ->and(array_diff($matches[1], array_keys(Artisan::all())))->toBe([]);
});

it('asks the services about connections from before only once the deploy has opened the site again', function (): void {
    $lines = array_map(trim(...), explode("\n", connectionsGuideText('.docker/production/images/configs/app/post-deploy.sh')));
    $opens = array_search('php artisan up || true', $lines, strict: true);
    $asks = array_search('php artisan connections:identify || true', $lines, strict: true);

    expect($opens)->toBeInt()
        ->and($asks)->toBeInt()
        ->and($asks > $opens)->toBeTrue();
});

it('names in the privacy policy the services that keep only the number, and the ones that keep keys', function (): void {
    $legal = connectionsGuideText('apps/web/src/legal.ts');

    preg_match('/For ([^.]+?), it keeps only the number\./', $legal, $numberOnly);
    preg_match('/Only ([^.]+?) give Traceway keys that it keeps/', $legal, $keys);

    $labels = fn (callable $keep): array => array_values(array_map(
        fn (ConnectionProvider $connectionProvider): string => $connectionProvider->label(),
        array_filter(ConnectionProvider::cases(), $keep),
    ));

    expect(connectionsGuideNames($numberOnly[1] ?? ''))
        ->toEqualCanonicalizing($labels(fn (ConnectionProvider $connectionProvider): bool => ! $connectionProvider->keepsAccountLabel()))
        ->and(connectionsGuideNames($keys[1] ?? ''))
        ->toEqualCanonicalizing($labels(fn (ConnectionProvider $connectionProvider): bool => $connectionProvider->keepsKeys()));
});

it('lists in the cookie policy the cookies that logging in with another service sets', function (): void {
    $legal = connectionsGuideText('apps/web/src/legal.ts');
    $names = [];

    foreach (File::allFiles(app_path()) as $file) {
        preg_match_all("/const string \\w*COOKIE\\w* = '([A-Za-z0-9_-]+)'/", $file->getContents(), $matches);
        $names = [...$names, ...$matches[1]];
    }

    expect($names)->toContain('last_sign_in')
        ->and(array_values(array_filter($names, fn (string $name): bool => ! str_contains($legal, '**'.$name.'**'))))->toBe([])
        ->and($legal)->toContain('the link connects the service only in this browser');
});

it('says in the privacy policy how long a sign-up waiting for its email link is kept', function (): void {
    expect(connectionsGuideText('apps/web/src/legal.ts'))
        ->toContain('emails you a link that works for 60 minutes')
        ->toContain('up to 60 minutes while the link Traceway emailed you to open your account still works');
});
