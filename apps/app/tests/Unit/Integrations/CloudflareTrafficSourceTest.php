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
                        'sum' => ['requests' => 1200, 'cachedRequests' => 900, 'bytes' => 5_000_000, 'pageViews' => 300, 'threats' => 4],
                        'uniq' => ['uniques' => 140],
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
        'date' => '2026-10-18', 'requests' => 1200, 'cached' => 900, 'bytes' => 5_000_000, 'page_views' => 300, 'threats' => 4, 'uniques' => 140,
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
