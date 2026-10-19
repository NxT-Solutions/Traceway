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
    $zoneVariables = [];
    $mockClient = new MockClient([
        AnalyticsQueryRequest::class => function (PendingRequest $pendingRequest) use (&$zoneVariables): MockResponse {
            $body = $pendingRequest->body()?->all();
            $variables = is_array($body) && is_array($body['variables'] ?? null) ? $body['variables'] : [];

            if (array_key_exists('zoneTag', $variables)) {
                $zoneVariables = $variables;

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
        ->report(CarbonImmutable::parse('2026-09-20'), CarbonImmutable::parse('2026-10-19'));

    expect($report->requestDays)->toBe([[
        'date' => '2026-10-18', 'requests' => 1200, 'cached' => 900, 'bytes' => 5_000_000, 'threats' => 4,
    ]])
        ->and($report->visitDays)->toBe([['date' => '2026-10-18', 'page_loads' => 80, 'visits' => 50]])
        ->and($report->topPages)->toBe([['label' => '/', 'count' => 60]])
        ->and($report->topReferrers)->toBe([['label' => '(none)', 'count' => 20]])
        ->and($report->countries)->toBe([['label' => 'Belgium', 'count' => 70]])
        ->and($zoneVariables)->toBe(['zoneTag' => 'zone', 'since' => '2026-09-20', 'until' => '2026-10-19']);
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

it('asks for visits from the start of the visit window at the earliest, in UTC', function (string $since, string $until, array $expected): void {
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
    'a month' => ['2026-09-20 00:00:00', '2026-10-19 14:30:00', [['2026-10-13T00:00:00Z', '2026-10-19T14:30:00Z']]],
    'one finished day' => ['2026-10-15 00:00:00', '2026-10-15 23:59:59', [['2026-10-15T00:00:00Z', '2026-10-15T23:59:59Z']]],
    'Brussels midnight' => ['2026-10-15 00:00:00 Europe/Brussels', '2026-10-19 12:00:00 Europe/Brussels', [['2026-10-14T22:00:00Z', '2026-10-19T10:00:00Z']]],
    'until before since' => ['2026-10-19 12:00:00', '2026-10-18 12:00:00', []],
]);
