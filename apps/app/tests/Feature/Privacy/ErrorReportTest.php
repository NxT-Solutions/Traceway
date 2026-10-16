<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;
use Sentry\Laravel\Tracing\ServiceProvider as SentryTracingServiceProvider;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Serializer\PayloadSerializer;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

use function Sentry\logger;

uses(RefreshDatabase::class);
uses()->group('gdpr');

/**
 * Starts Sentry from the app's own config, as production does with a DSN,
 * with traces and logs on, and a transport that keeps each payload instead
 * of sending it.
 *
 * @return ArrayObject<int, Event>
 */
function keepSentryEvents(): ArrayObject
{
    /** @var ArrayObject<int, Event> $events */
    $events = new ArrayObject;

    config([
        'sentry.dsn' => 'https://public@o1.ingest.de.sentry.io/1',
        'sentry.traces_sample_rate' => 1.0,
        'sentry.enable_logs' => true,
        'sentry.transport' => new readonly class($events) implements TransportInterface
        {
            /**
             * @param  ArrayObject<int, Event>  $arrayObject
             */
            public function __construct(private ArrayObject $arrayObject) {}

            public function send(Event $event): Result
            {
                $this->arrayObject->append($event);

                return new Result(ResultStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        },
        'logging.default' => 'stack',
        'logging.channels.stack.channels' => ['sentry_logs'],
    ]);

    app()->forgetInstance(HubInterface::class);
    Log::forgetChannel('stack');
    new SentryServiceProvider(app())->boot();
    new SentryTracingServiceProvider(app())->boot();
    SentrySdk::setCurrentHub(resolve(HubInterface::class));

    return $events;
}

/**
 * Every payload as Sentry would receive it.
 *
 * @param  ArrayObject<int, Event>  $arrayObject
 */
function sentPayloads(ArrayObject $arrayObject): string
{
    logger()->flush();

    $options = SentrySdk::getCurrentHub()->getClient()?->getOptions();
    $serializer = new PayloadSerializer($options ?? new Options);

    return implode("\n", array_map($serializer->serialize(...), $arrayObject->getArrayCopy()));
}

/**
 * @param  ArrayObject<int, Event>  $arrayObject
 */
function sentOfType(ArrayObject $arrayObject, EventType $eventType): ?Event
{
    foreach ($arrayObject as $event) {
        if ($event->getType() === $eventType) {
            return $event;
        }
    }

    return null;
}

/**
 * Rider data made at run time, so the source lines Sentry sends with a stack
 * frame cannot hold it.
 *
 * @return array{token: string, ip: string, email: string, latitude: string}
 */
function riderSecrets(): array
{
    return [
        'token' => bin2hex(random_bytes(16)),
        'ip' => '203.0.113.'.random_int(10, 250),
        'email' => bin2hex(random_bytes(6)).'@traceway.test',
        'latitude' => '51.'.random_int(100_000, 999_999),
    ];
}

it('sends Sentry the fault, not the rider', function (): void {
    $events = keepSentryEvents();
    $secret = riderSecrets();
    $user = User::factory()->create(['email' => $secret['email']]);
    Route::post('/_test/report/{token}', fn () => throw new RuntimeException('The map server is down.'))
        ->middleware('web');

    $this->actingAs($user)
        ->withUnencryptedCookie('appearance', 'dark')
        ->withHeaders([
            'CF-Connecting-IP' => $secret['ip'],
            'X-Forwarded-For' => $secret['ip'],
            'User-Agent' => 'Traceway test browser',
        ])
        ->post(sprintf('/_test/report/%s?near=%s,4.92', $secret['token'], $secret['latitude']), [
            'email' => $secret['email'],
            'name' => 'Morning loop',
        ])
        ->assertInternalServerError();

    $sent = sentPayloads($events);
    $error = sentOfType($events, EventType::event());

    expect($error)->not->toBeNull();

    $request = $error?->getRequest() ?? [];
    $headers = array_change_key_case($request['headers'] ?? [], CASE_LOWER);

    expect($error?->getExceptions()[0]->getValue())->toBe('The map server is down.')
        ->and($error?->getUser())->toBeNull()
        ->and($request['url'] ?? null)->toEndWith('/_test/report/{token}')
        ->and($request)->not->toHaveKeys(['data', 'cookies', 'env', 'query_string'])
        ->and($headers['user-agent'] ?? null)->toBe(['Traceway test browser'])
        ->and($headers['cf-connecting-ip'] ?? null)->toBe(['[Filtered]'])
        ->and($headers)->not->toHaveKey('cookie')
        ->and($sent)->not->toContain($secret['token'])
        ->and($sent)->not->toContain($secret['ip'])
        ->and($sent)->not->toContain($secret['email'])
        ->and($sent)->not->toContain($secret['latitude'])
        ->and($sent)->not->toContain('"userId"');
});

it('keeps the rider out of the trace and the logs of a handled fault', function (): void {
    $events = keepSentryEvents();
    $secret = riderSecrets();
    $user = User::factory()->create(['email' => $secret['email']]);
    Route::get('/_test/handled/{token}', function (): string {
        report(new RuntimeException('The place search failed.'));

        return 'ok';
    })->middleware('web');

    $this->actingAs($user)
        ->get('/_test/handled/'.$secret['token'])
        ->assertOk();

    $sent = sentPayloads($events);
    $transaction = sentOfType($events, EventType::transaction());
    $logs = sentOfType($events, EventType::logs());

    expect($transaction)->not->toBeNull()
        ->and($logs)->not->toBeNull()
        ->and($transaction?->getTransaction())->toBe('/_test/handled/{token}')
        ->and($transaction?->getContexts()['trace']['data']['url'] ?? null)->toBe('/_test/handled/{token}')
        ->and($sent)->toContain('The place search failed.')
        ->and($sent)->not->toContain($secret['token'])
        ->and($sent)->not->toContain($secret['email'])
        ->and($sent)->not->toContain('"userId"');
});
