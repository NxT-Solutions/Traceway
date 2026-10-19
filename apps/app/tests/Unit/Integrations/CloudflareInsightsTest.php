<?php

declare(strict_types=1);

use App\Domain\Traffic\TrafficUnavailable;
use App\Integrations\Cloudflare\CloudflareAnalyticsConnector;
use App\Integrations\Cloudflare\CloudflareGraphql;
use App\Integrations\Cloudflare\CloudflareInsights;
use App\Integrations\Cloudflare\Requests\AnalyticsQueryRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Cloudflare that answers each question with these aliases for the zone or the account, and keeps the variables
 * it was sent.
 *
 * @param  array<string, mixed>  $zone
 * @param  array<string, mixed>  $account
 * @param  list<array<string, mixed>>|null  $sent
 */
function insights(array $zone = [], array $account = [], ?array &$sent = null, ?string $accountId = 'account'): CloudflareInsights
{
    $sent = [];
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient(new MockClient([
        AnalyticsQueryRequest::class => function (PendingRequest $pendingRequest) use ($zone, $account, &$sent): MockResponse {
            $body = $pendingRequest->body()?->all();
            $variables = is_array($body) && is_array($body['variables'] ?? null) ? $body['variables'] : [];
            $sent[] = $variables;

            return MockResponse::make(['data' => ['viewer' => array_key_exists('zoneTag', $variables)
                ? ['zones' => [$zone]]
                : ['accounts' => [$account]]]]);
        },
    ]));

    return new CloudflareInsights(new CloudflareGraphql($connector), 'zone', $accountId, 'site', 'traceway-backups', 'site-key');
}

function insightsNow(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-10-19 12:00:00', new DateTimeZone('UTC'));
}

it("tells verified bots from scanners that only use a bot's name", function (): void {
    $insights = insights([
        'verified' => [
            ['count' => 94, 'dimensions' => ['verifiedBotCategory' => 'Search Engine Crawler']],
            ['count' => 56, 'dimensions' => ['verifiedBotCategory' => 'AI Search']],
        ],
        'impostors' => [
            ['count' => 84, 'dimensions' => ['userAgent' => 'Mozilla/5.0 (compatible; GPTBot/1.4; +https://openai.com/gptbot)']],
            ['count' => 54, 'dimensions' => ['userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']],
            ['count' => 44, 'dimensions' => ['userAgent' => 'Mozilla/5.0 (compatible; Meta-ExternalAgent/1.0)']],
            ['count' => 10, 'dimensions' => ['userAgent' => 'meta-externalagent/1.1']],
            ['count' => 9, 'dimensions' => ['userAgent' => 'curl/8.7.1']],
        ],
        'impostorsTotal' => [['count' => 2135]],
        'impostorsBlocked' => [['count' => 1088]],
    ], sent: $sent);

    $bots = $insights->bots(insightsNow());

    expect($bots->verified)->toBe([
        ['label' => 'Search Engine Crawler', 'count' => 94],
        ['label' => 'AI Search', 'count' => 56],
    ])
        ->and($bots->impostors)->toBe([
            ['label' => 'GPTBot', 'count' => 84],
            ['label' => 'Googlebot', 'count' => 54],
            ['label' => 'Meta-ExternalAgent', 'count' => 54],
        ])
        ->and($bots->impostorsTotal)->toBe(2135)
        ->and($bots->impostorsBlocked)->toBe(1088)
        ->and($sent[0]['impostors'])->toMatchArray([
            'datetime_geq' => '2026-09-20T00:00:00Z',
            'datetime_leq' => '2026-10-19T12:00:00Z',
            'verifiedBotCategory' => '',
        ])
        ->and($sent[0]['blocked'])->toMatchArray(['verifiedBotCategory' => '', 'edgeResponseStatus' => 403])
        ->and($sent[0]['verified'])->toMatchArray(['verifiedBotCategory_neq' => '']);
});

it('counts probes for secret files by status, and flags a file that came back', function (): void {
    $insights = insights([
        'probes' => [
            ['count' => 3592, 'dimensions' => ['edgeResponseStatus' => 403]],
            ['count' => 483, 'dimensions' => ['edgeResponseStatus' => 404]],
            ['count' => 1, 'dimensions' => ['edgeResponseStatus' => 200]],
        ],
        'exposed' => [['count' => 1, 'dimensions' => ['clientRequestPath' => '/.env']]],
    ], sent: $sent);

    $bots = $insights->bots(insightsNow());

    expect($bots->probes)->toBe([
        ['status' => 403, 'count' => 3592],
        ['status' => 404, 'count' => 483],
        ['status' => 200, 'count' => 1],
    ])
        ->and($bots->exposed)->toBe([['label' => '/.env', 'count' => 1]])
        ->and($sent[0]['probes'])->toMatchArray(['clientRequestPath_neq' => '/build/manifest.json'])
        ->and(array_column($sent[0]['probes']['OR'], 'clientRequestPath_like'))->toContain('%.env%', '%id_rsa%', '%.git/%', '%tfstate%')
        ->and($sent[0]['exposed'])->toMatchArray(['edgeResponseStatus_geq' => 200, 'edgeResponseStatus_lt' => 300]);
});

it('adds up the reads of what Traceway publishes for assistants, and who sent them', function (): void {
    $insights = insights([
        'reads' => [
            ['count' => 78, 'dimensions' => ['clientRequestPath' => '/robots.txt', 'clientRequestHTTPMethodName' => 'GET', 'edgeResponseStatus' => 200]],
            ['count' => 2, 'dimensions' => ['clientRequestPath' => '/mcp', 'clientRequestHTTPMethodName' => 'GET', 'edgeResponseStatus' => 405]],
        ],
        'readsTotal' => [['count' => 195]],
        'readers' => [
            ['count' => 43, 'dimensions' => ['verifiedBotCategory' => 'Search Engine Crawler', 'userAgent' => 'Googlebot/2.1']],
            ['count' => 2, 'dimensions' => ['verifiedBotCategory' => '', 'userAgent' => 'curl/8.7.1']],
            ['count' => 1, 'dimensions' => ['verifiedBotCategory' => '', 'userAgent' => 'Mozilla/5.0 (compatible; ChatGPT-User/1.0)']],
        ],
    ], sent: $sent);

    $bots = $insights->bots(insightsNow());

    expect($bots->reads)->toBe([
        ['path' => '/robots.txt', 'method' => 'GET', 'status' => 200, 'count' => 78],
        ['path' => '/mcp', 'method' => 'GET', 'status' => 405, 'count' => 2],
    ])
        ->and($bots->readsTotal)->toBe(195)
        ->and($bots->readers)->toBe([
            ['label' => 'Search Engine Crawler', 'count' => 43],
            ['label' => 'Other clients', 'count' => 2],
            ['label' => 'ChatGPT-User, unverified', 'count' => 1],
        ])
        ->and($sent[0]['reads']['OR'])->toBe([
            ['clientRequestPath_in' => ['/llms.txt', '/llms-full.txt', '/robots.txt', '/mcp']],
            ['clientRequestPath_like' => '%.md'],
            ['clientRequestPath_like' => '/.well-known/oauth-%'],
        ]);
});

it('adds up the firewall events, and finds blocks on pages people use', function (): void {
    $event = fn (string $path, string $country, string $source = 'firewallCustom'): array => [
        'action' => 'block', 'source' => $source, 'clientCountryName' => $country, 'clientRequestPath' => $path, 'userAgent' => 'curl/8.7.1', 'ruleId' => $source === 'firewallCustom' ? 'custom-rule' : 'managed-rule',
    ];
    $insights = insights([
        'days' => [
            ['dimensions' => ['date' => '2026-10-18'], 'sum' => ['threats' => 5, 'countryMap' => [['clientCountryName' => 'FR', 'threats' => 4], ['clientCountryName' => 'BE', 'threats' => 1]]]],
            ['dimensions' => ['date' => '2026-10-19'], 'sum' => ['threats' => 5, 'countryMap' => [['clientCountryName' => 'BE', 'threats' => 5]]]],
        ],
        'blocks' => [
            ['count' => 770, 'dimensions' => ['date' => '2026-10-18']],
            ['count' => 470, 'dimensions' => ['date' => '2026-10-19']],
        ],
        'events' => [
            $event('/.env', 'US'),
            $event('/.env', 'BE'),
            $event('/', 'BE'),
            $event('/login', 'BE'),
            $event('/wp-login.php', 'NL', 'firewallManaged'),
        ],
    ], [
        'turnstile' => [
            ['count' => 11, 'dimensions' => ['eventType' => 'challenge_issued']],
            ['count' => 3, 'dimensions' => ['eventType' => 'challenge_non_interactive_solved']],
        ],
    ], $sent);

    $security = $insights->security(insightsNow());

    expect($security->threatDays)->toBe([['date' => '2026-10-18', 'count' => 5], ['date' => '2026-10-19', 'count' => 5]])
        ->and($security->threatCountries)->toBe([['label' => 'BE', 'count' => 6], ['label' => 'FR', 'count' => 4]])
        ->and($security->blockDays)->toBe([['date' => '2026-10-18', 'count' => 770], ['date' => '2026-10-19', 'count' => 470]])
        ->and($security->events)->toBe(5)
        ->and($security->sources)->toBe([['label' => 'firewallCustom', 'count' => 4], ['label' => 'firewallManaged', 'count' => 1]])
        ->and($security->countries[0])->toBe(['label' => 'BE', 'count' => 3])
        ->and($security->paths[0])->toBe(['label' => '/.env', 'count' => 2])
        ->and($security->rules)->toBe([['label' => 'custom-rule', 'count' => 4], ['label' => 'managed-rule', 'count' => 1]])
        ->and($security->peoplePages)->toBe([['label' => '/', 'count' => 1], ['label' => '/login', 'count' => 1]])
        ->and($security->turnstile)->toBe([
            ['label' => 'challenge_issued', 'count' => 11],
            ['label' => 'challenge_non_interactive_solved', 'count' => 3],
        ])
        ->and($sent[0])->toMatchArray(['sinceDate' => '2026-09-20', 'untilDate' => '2026-10-19'])
        ->and($sent[1]['filter'])->toMatchArray(['siteKey' => 'site-key', 'datetime_leq' => '2026-10-19T12:00:00Z']);
});

it('skips the sign-up checks without an account id', function (): void {
    $insights = insights(['events' => []], sent: $sent, accountId: null);

    expect($insights->security(insightsNow())->turnstile)->toBe([])
        ->and($sent)->toHaveCount(1);
});

it('adds up the last 30 days of a year by kind, and keeps every day', function (): void {
    $day = fn (string $date, int $requests): array => ['dimensions' => ['date' => $date], 'sum' => [
        'requests' => $requests,
        'threats' => 1,
        'browserMap' => [['uaBrowserFamily' => 'Chrome', 'pageViews' => 5]],
        'responseStatusMap' => [['edgeResponseStatus' => 200, 'requests' => 3], ['edgeResponseStatus' => 404, 'requests' => 7]],
        'contentTypeMap' => [['edgeResponseContentTypeName' => 'html', 'requests' => $requests]],
        'clientHTTPVersionMap' => [['clientHTTPProtocol' => 'HTTP/2', 'requests' => $requests]],
        'ipClassMap' => [['ipType' => 'noRecord', 'requests' => $requests]],
    ]];
    $insights = insights([
        'days' => [$day('2026-01-02', 1000), $day('2026-10-18', 10), $day('2026-10-19', 20)],
        'hours' => [['dimensions' => ['datetime' => '2026-10-19T11:00:00Z'], 'sum' => ['requests' => 42, 'threats' => 2]]],
    ], sent: $sent);

    $requests = $insights->requests(insightsNow());

    expect($requests->days)->toHaveCount(3)
        ->and($requests->hours)->toBe([['time' => '2026-10-19T11:00:00Z', 'requests' => 42, 'threats' => 2]])
        ->and($requests->statuses)->toBe([['status' => 404, 'count' => 14], ['status' => 200, 'count' => 6]])
        ->and($requests->contentTypes)->toBe([['label' => 'html', 'count' => 30]])
        ->and($requests->browsers)->toBe([['label' => 'Chrome', 'count' => 10]])
        ->and($sent[0])->toMatchArray([
            'sinceDate' => '2025-10-20',
            'sinceHour' => '2026-10-16T13:00:00Z',
            'until' => '2026-10-19T12:00:00Z',
        ]);
});

it('reads Core Web Vitals in milliseconds, and nothing measured as null', function (): void {
    $insights = insights(account: [
        'vitals' => [
            ['count' => 17, 'dimensions' => ['requestPath' => '/'], 'quantiles' => ['largestContentfulPaintP75' => 1564869, 'interactionToNextPaintP75' => 222000, 'cumulativeLayoutShiftP75' => 0]],
            ['count' => 1, 'dimensions' => ['requestPath' => '/support'], 'quantiles' => ['largestContentfulPaintP75' => 216000, 'interactionToNextPaintP75' => -1, 'cumulativeLayoutShiftP75' => -1]],
        ],
        'timings' => [
            ['count' => 15, 'dimensions' => ['requestPath' => '/'], 'quantiles' => ['pageLoadTimeP75' => 498000, 'firstContentfulPaintP75' => 0]],
        ],
    ], sent: $sent);

    $speed = $insights->speed(insightsNow());

    expect($speed->vitals)->toBe([
        ['path' => '/', 'loads' => 17, 'lcp_ms' => 1564.9, 'inp_ms' => 222.0, 'cls' => 0.0],
        ['path' => '/support', 'loads' => 1, 'lcp_ms' => 216.0, 'inp_ms' => null, 'cls' => null],
    ])
        ->and($speed->timings)->toBe([['path' => '/', 'loads' => 15, 'load_ms' => 498.0, 'first_paint_ms' => null]])
        ->and($sent[0]['filter']['AND'])->toBe([
            ['siteTag' => 'site'],
            ['datetime_geq' => '2026-10-13T00:00:00Z'],
            ['datetime_leq' => '2026-10-19T12:00:00Z'],
        ]);
});

it('says when the backups were last written, and how big the bucket is', function (): void {
    $insights = insights(account: [
        'storage' => [['max' => ['objectCount' => 4248, 'payloadSize' => 39887056, 'metadataSize' => 368761]]],
        'writes' => [
            ['sum' => ['requests' => 3010], 'dimensions' => ['date' => '2026-10-17']],
            ['sum' => ['requests' => 631], 'dimensions' => ['date' => '2026-10-14']],
            ['sum' => ['requests' => 0], 'dimensions' => ['date' => '2026-10-18']],
        ],
    ], sent: $sent);

    $backups = $insights->backups(insightsNow());

    expect($backups->bucket)->toBe('traceway-backups')
        ->and($backups->objects)->toBe(4248)
        ->and($backups->bytes)->toBe(40255817)
        ->and($backups->writeDays)->toBe([
            ['date' => '2026-10-14', 'count' => 631],
            ['date' => '2026-10-17', 'count' => 3010],
            ['date' => '2026-10-18', 'count' => 0],
        ])
        ->and($backups->lastWrite)->toBe('2026-10-17')
        ->and($sent[0]['writes'])->toMatchArray(['bucketName' => 'traceway-backups', 'actionType_in' => ['PutObject', 'CompleteMultipartUpload', 'CopyObject']]);
});

it('says so when Cloudflare refuses a question', function (): void {
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient(new MockClient([
        AnalyticsQueryRequest::class => MockResponse::make(['data' => null, 'errors' => [['message' => 'does not have access to the path']]]),
    ]));

    new CloudflareInsights(new CloudflareGraphql($connector), 'zone', 'account', 'site', 'traceway-backups', turnstileSiteKey: null)->bots(insightsNow());
})->throws(TrafficUnavailable::class);

it('says so when Cloudflare answers with an error page', function (): void {
    $connector = new CloudflareAnalyticsConnector('token');
    $connector->withMockClient(new MockClient([
        AnalyticsQueryRequest::class => MockResponse::make('<html>Bad gateway</html>', 502, ['Content-Type' => 'text/html']),
    ]));

    new CloudflareInsights(new CloudflareGraphql($connector), 'zone', 'account', 'site', 'traceway-backups', turnstileSiteKey: null)->speed(insightsNow());
})->throws(TrafficUnavailable::class);

it('asks nothing for a part whose id is not set', function (): void {
    $connector = new CloudflareAnalyticsConnector('token');
    $mockClient = new MockClient([AnalyticsQueryRequest::class => MockResponse::make([])]);
    $connector->withMockClient($mockClient);
    $insights = new CloudflareInsights(new CloudflareGraphql($connector), zoneId: null, accountId: null, siteTag: null, backupBucket: null, turnstileSiteKey: null);

    expect($insights->bots(insightsNow())->verified)->toBe([])
        ->and($insights->speed(insightsNow())->vitals)->toBe([])
        ->and($insights->backups(insightsNow())->bucket)->toBeNull();
    $mockClient->assertNothingSent();
});
