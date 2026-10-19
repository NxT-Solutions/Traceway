<?php

declare(strict_types=1);

use App\Domain\Traffic\TrafficUnavailable;
use App\Integrations\Cloudflare\CloudflareAnalyticsConnector;
use App\Integrations\Cloudflare\CloudflareTrafficSource;
use App\Integrations\Cloudflare\Requests\AnalyticsQueryRequest;
use Carbon\CarbonImmutable;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\TestCase;

uses(TestCase::class);

it('reads daily requests and visits from the Analytics API', function (): void {
    $mockClient = new MockClient([
        AnalyticsQueryRequest::class => function (PendingRequest $pendingRequest): MockResponse {
            $body = $pendingRequest->body()?->all();
            $variables = is_array($body) && is_array($body['variables'] ?? null) ? $body['variables'] : [];

            if (array_key_exists('zoneTag', $variables)) {
                return MockResponse::make(['data' => ['viewer' => ['zones' => [[
                    'httpRequests1dGroups' => [[
                        'dimensions' => ['date' => '2026-10-18'],
                        'sum' => ['requests' => 1200, 'cachedRequests' => 900, 'bytes' => 5_000_000, 'threats' => 4],
                    ]],
                ]]]]]);
            }

            return MockResponse::make(['data' => ['viewer' => ['accounts' => [[
                'days' => [['count' => 80, 'sum' => ['visits' => 50], 'dimensions' => ['date' => '2026-10-18']]],
                'pages' => [['count' => 60, 'dimensions' => ['requestPath' => '/']]],
                'referrers' => [['count' => 20, 'dimensions' => ['refererHost' => '']]],
                'countries' => [['count' => 70, 'dimensions' => ['countryName' => 'Belgium']]],
            ]]]]]);
        },
    ]);
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient($mockClient);

    $report = new CloudflareTrafficSource($connector, 'zone', 'account', 'site')
        ->report(CarbonImmutable::parse('2026-10-13'), CarbonImmutable::parse('2026-10-19'));

    expect($report->requestDays)->toBe([[
        'date' => '2026-10-18', 'requests' => 1200, 'cached' => 900, 'bytes' => 5_000_000, 'threats' => 4,
    ]])
        ->and($report->visitDays)->toBe([['date' => '2026-10-18', 'page_loads' => 80, 'visits' => 50]])
        ->and($report->topPages)->toBe([['label' => '/', 'count' => 60]])
        ->and($report->topReferrers)->toBe([['label' => '(none)', 'count' => 20]])
        ->and($report->countries)->toBe([['label' => 'Belgium', 'count' => 70]]);
    $mockClient->assertSentCount(2);
});

it('asks only for requests when there is no site tag', function (): void {
    $mockClient = new MockClient([
        AnalyticsQueryRequest::class => MockResponse::make(['data' => ['viewer' => ['zones' => [['httpRequests1dGroups' => []]]]]]),
    ]);
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient($mockClient);

    $report = new CloudflareTrafficSource($connector, 'zone', accountId: null, siteTag: null)
        ->report(CarbonImmutable::parse('2026-09-20'), CarbonImmutable::parse('2026-10-19'));

    expect($report->visitDays)->toBe([]);
    $mockClient->assertSentCount(1);
});

it('says so when Cloudflare refuses the token', function (): void {
    $connector = new CloudflareAnalyticsConnector('bad');
    $connector->withMockClient(new MockClient([
        AnalyticsQueryRequest::class => MockResponse::make(['data' => null, 'errors' => [['message' => 'not authorized']]]),
    ]));

    new CloudflareTrafficSource($connector, 'zone', accountId: null, siteTag: null)
        ->report(CarbonImmutable::parse('2026-09-20'), CarbonImmutable::parse('2026-10-19'));
})->throws(TrafficUnavailable::class);

it('says so when Cloudflare answers with an error page', function (): void {
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient(new MockClient([
        AnalyticsQueryRequest::class => MockResponse::make('<html>Bad gateway</html>', 502, ['Content-Type' => 'text/html']),
    ]));

    new CloudflareTrafficSource($connector, 'zone', accountId: null, siteTag: null)
        ->report(CarbonImmutable::parse('2026-09-20'), CarbonImmutable::parse('2026-10-19'));
})->throws(TrafficUnavailable::class);

it('says so when Cloudflare answers with something that is not JSON', function (): void {
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient(new MockClient([
        AnalyticsQueryRequest::class => MockResponse::make('not json', 200, ['Content-Type' => 'text/plain']),
    ]));

    new CloudflareTrafficSource($connector, 'zone', accountId: null, siteTag: null)
        ->report(CarbonImmutable::parse('2026-09-20'), CarbonImmutable::parse('2026-10-19'));
})->throws(TrafficUnavailable::class);

it('asks for the last seven days on their own and the weeks before them one at a time', function (): void {
    $windows = [];
    $mockClient = new MockClient([
        AnalyticsQueryRequest::class => function (PendingRequest $pendingRequest) use (&$windows): MockResponse {
            $body = $pendingRequest->body()?->all();
            $variables = is_array($body) && is_array($body['variables'] ?? null) ? $body['variables'] : [];

            if (array_key_exists('zoneTag', $variables)) {
                return MockResponse::make(['data' => ['viewer' => ['zones' => [['httpRequests1dGroups' => []]]]]]);
            }

            /** @var array{AND: array{0: array{siteTag: string}, 1: array{datetime_geq: string}, 2: array{datetime_leq: string}}} $filter */
            $filter = $variables['filter'];
            $windows[] = [$filter['AND'][1]['datetime_geq'], $filter['AND'][2]['datetime_leq']];

            return MockResponse::make(['data' => ['viewer' => ['accounts' => [['days' => [], 'pages' => [], 'referrers' => [], 'countries' => []]]]]]);
        },
    ]);
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient($mockClient);

    new CloudflareTrafficSource($connector, 'zone', 'account', 'site')
        ->report(CarbonImmutable::parse('2026-09-20 00:00:00'), CarbonImmutable::parse('2026-10-19 14:30:00'));

    $mockClient->assertSentCount(6);
    expect($windows)->toBe([
        ['2026-09-20T00:00:00Z', '2026-09-21T23:59:59Z'],
        ['2026-09-22T00:00:00Z', '2026-09-28T23:59:59Z'],
        ['2026-09-29T00:00:00Z', '2026-10-05T23:59:59Z'],
        ['2026-10-06T00:00:00Z', '2026-10-12T23:59:59Z'],
        ['2026-10-13T00:00:00Z', '2026-10-19T14:30:00Z'],
    ]);

    foreach ($windows as [$from, $to]) {
        expect(CarbonImmutable::parse($from)->diffInSeconds(CarbonImmutable::parse($to)))->toBeLessThanOrEqual(7 * 24 * 3600);
    }
});

it('adds up the weeks of visits, pages, referrers, and countries', function (): void {
    $answers = [
        [
            'days' => [
                ['count' => 10, 'sum' => ['visits' => 5], 'dimensions' => ['date' => '2026-10-15']],
                ['count' => 2, 'sum' => ['visits' => 1], 'dimensions' => ['date' => '2026-10-17']],
            ],
            'pages' => [
                ['count' => 9, 'dimensions' => ['requestPath' => '/']],
                ['count' => 3, 'dimensions' => ['requestPath' => '/privacy']],
            ],
            'referrers' => [['count' => 4, 'dimensions' => ['refererHost' => 'duckduckgo.com']]],
            'countries' => [
                ['count' => 8, 'dimensions' => ['countryName' => 'Belgium']],
                ['count' => 4, 'dimensions' => ['countryName' => 'France']],
            ],
        ],
        [
            'days' => [
                ['count' => 6, 'sum' => ['visits' => 5], 'dimensions' => ['date' => '2026-10-17']],
                ['count' => 1, 'sum' => ['visits' => 1], 'dimensions' => ['date' => '2026-10-18']],
            ],
            'pages' => [
                ['count' => 5, 'dimensions' => ['requestPath' => '/privacy']],
                ['count' => 2, 'dimensions' => ['requestPath' => '/']],
            ],
            'referrers' => [['count' => 7, 'dimensions' => ['refererHost' => '']]],
            'countries' => [['count' => 7, 'dimensions' => ['countryName' => 'France']]],
        ],
    ];
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient(new MockClient([
        AnalyticsQueryRequest::class => function () use (&$answers): MockResponse {
            return MockResponse::make(['data' => ['viewer' => ['accounts' => [array_shift($answers)]]]]);
        },
    ]));

    $report = new CloudflareTrafficSource($connector, zoneId: null, accountId: 'account', siteTag: 'site')
        ->report(CarbonImmutable::parse('2026-10-11'), CarbonImmutable::parse('2026-10-18 12:00:00'));

    expect($report->visitDays)->toBe([
        ['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5],
        ['date' => '2026-10-17', 'page_loads' => 8, 'visits' => 6],
        ['date' => '2026-10-18', 'page_loads' => 1, 'visits' => 1],
    ])
        ->and($report->topPages)->toBe([['label' => '/', 'count' => 11], ['label' => '/privacy', 'count' => 8]])
        ->and($report->topReferrers)->toBe([['label' => '(none)', 'count' => 7], ['label' => 'duckduckgo.com', 'count' => 4]])
        ->and($report->countries)->toBe([['label' => 'France', 'count' => 11], ['label' => 'Belgium', 'count' => 8]]);
});

it('keeps the ten biggest of each list', function (): void {
    $pages = array_map(
        fn (int $index): array => ['count' => $index, 'dimensions' => ['requestPath' => '/'.$index]],
        range(1, 12),
    );
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient(new MockClient([
        AnalyticsQueryRequest::class => MockResponse::make(['data' => ['viewer' => ['accounts' => [
            ['days' => [], 'pages' => $pages, 'referrers' => [], 'countries' => []],
        ]]]]),
    ]));

    $report = new CloudflareTrafficSource($connector, zoneId: null, accountId: 'account', siteTag: 'site')
        ->report(CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-18'));

    expect($report->topPages)->toHaveCount(10)
        ->and($report->topPages[0])->toBe(['label' => '/12', 'count' => 24])
        ->and($report->topPages[9])->toBe(['label' => '/3', 'count' => 6]);
});

it('asks Cloudflare in UTC and sends no visits question for an empty stretch', function (string $since, string $until, array $expected): void {
    $windows = [];
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient(new MockClient([
        AnalyticsQueryRequest::class => function (PendingRequest $pendingRequest) use (&$windows): MockResponse {
            $body = $pendingRequest->body()?->all();
            /** @var array{filter: array{AND: array{0: array{siteTag: string}, 1: array{datetime_geq: string}, 2: array{datetime_leq: string}}}} $variables */
            $variables = is_array($body) && is_array($body['variables'] ?? null) ? $body['variables'] : [];
            $windows[] = [$variables['filter']['AND'][1]['datetime_geq'], $variables['filter']['AND'][2]['datetime_leq']];

            return MockResponse::make(['data' => ['viewer' => ['accounts' => [['days' => [], 'pages' => [], 'referrers' => [], 'countries' => []]]]]]);
        },
    ]));

    new CloudflareTrafficSource($connector, zoneId: null, accountId: 'account', siteTag: 'site')
        ->report(CarbonImmutable::parse($since), CarbonImmutable::parse($until));

    expect($windows)->toBe($expected);
})->with([
    'Brussels midnight' => ['2026-10-15 00:00:00 Europe/Brussels', '2026-10-19 12:00:00 Europe/Brussels', [['2026-10-14T22:00:00Z', '2026-10-19T10:00:00Z']]],
    'until before since' => ['2026-10-19 12:00:00', '2026-10-18 12:00:00', []],
]);
