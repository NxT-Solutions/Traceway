<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Sentry\Event;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;

uses(RefreshDatabase::class);
uses()->group('gdpr');

/**
 * Starts Sentry from the app's own config, as production does with a DSN,
 * but keeps each event instead of sending it.
 *
 * @return ArrayObject<int, Event>
 */
function keepSentryEvents(): ArrayObject
{
    /** @var ArrayObject<int, Event> $events */
    $events = new ArrayObject;

    config([
        'sentry.dsn' => 'https://public@sentry.invalid/1',
        'sentry.before_send' => static function (Event $event) use ($events): ?Event {
            $events->append($event);

            return null;
        },
    ]);

    app()->forgetInstance(HubInterface::class);
    new SentryServiceProvider(app())->boot();
    SentrySdk::setCurrentHub(resolve(HubInterface::class));

    return $events;
}

it('sends Sentry the fault, not the rider', function (): void {
    $events = keepSentryEvents();
    $user = User::factory()->create(['email' => 'rider@traceway.test']);
    Route::post('/_test/report', fn () => throw new RuntimeException('The map server is down.'))
        ->middleware('web');

    $this->actingAs($user)
        ->withUnencryptedCookie('appearance', 'dark')
        ->withHeaders([
            'CF-Connecting-IP' => '203.0.113.9',
            'X-Forwarded-For' => '203.0.113.9',
            'User-Agent' => 'Traceway test browser',
        ])
        ->post('/_test/report?near=51.08,4.92', [
            'email' => 'rider@traceway.test',
            'name' => 'Morning loop',
        ])
        ->assertInternalServerError();

    expect($events)->toHaveCount(1);

    $event = $events[0];
    $request = $event->getRequest();
    $headers = array_change_key_case($request['headers'] ?? [], CASE_LOWER);
    $sent = json_encode($request, JSON_THROW_ON_ERROR);

    expect($event->getExceptions()[0]->getValue())->toBe('The map server is down.')
        ->and($event->getUser())->toBeNull()
        ->and($request)->not->toHaveKeys(['data', 'cookies', 'env', 'query_string'])
        ->and($headers['user-agent'] ?? null)->toBe(['Traceway test browser'])
        ->and($headers['cf-connecting-ip'] ?? null)->toBe(['[Filtered]'])
        ->and($headers['x-forwarded-for'] ?? null)->toBe(['[Filtered]'])
        ->and($headers)->not->toHaveKey('cookie')
        ->and($sent)->not->toContain('203.0.113.9')
        ->and($sent)->not->toContain('rider@traceway.test')
        ->and($sent)->not->toContain('51.08');
});
