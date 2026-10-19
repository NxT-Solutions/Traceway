<?php

declare(strict_types=1);

use App\Domain\Traffic\BotNames;
use App\Domain\Traffic\PeoplePages;
use App\Domain\Traffic\VitalRating;
use App\Domain\Traffic\WebVital;

it('reads the bot a user agent claims to be', function (string $userAgent, ?string $claimed): void {
    expect(BotNames::claimed($userAgent))->toBe($claimed);
})->with([
    'GPTBot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.4; +https://openai.com/gptbot', 'GPTBot'],
    'Googlebot on a phone' => ['Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Googlebot'],
    'a family member' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot', 'OAI-SearchBot'],
    'another case' => ['meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)', 'Meta-externalagent'],
    'Claude-User, not ClaudeBot' => ['Mozilla/5.0 (compatible; Claude-User/1.0; +Claude-User@anthropic.com)', 'Claude-User'],
    'a browser' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36', null],
    'curl' => ['curl/8.7.1', null],
]);

it('gives Cloudflare one pattern for each name', function (): void {
    expect(BotNames::likePatterns())->toHaveCount(count(BotNames::NAMES))
        ->and(BotNames::likePatterns())->toContain('%GPTBot%', '%OAI-%');
});

it('rates a Core Web Vital as Google does', function (WebVital $webVital, float $value, VitalRating $vitalRating): void {
    expect($webVital->rating($value))->toBe($vitalRating);
})->with([
    'LCP at the good limit' => [WebVital::Lcp, 2500, VitalRating::Good],
    'LCP just over it' => [WebVital::Lcp, 2501, VitalRating::NeedsWork],
    'LCP past poor' => [WebVital::Lcp, 4001, VitalRating::Poor],
    'INP that needs work' => [WebVital::Inp, 222, VitalRating::NeedsWork],
    'INP that is poor' => [WebVital::Inp, 501, VitalRating::Poor],
    'no layout shift' => [WebVital::Cls, 0, VitalRating::Good],
    'a poor layout shift' => [WebVital::Cls, 0.3, VitalRating::Poor],
]);

it('knows the pages people use from the paths scanners try', function (): void {
    expect(PeoplePages::is('/'))->toBeTrue()
        ->and(PeoplePages::is('/login'))->toBeTrue()
        ->and(PeoplePages::is('/share/abc'))->toBeTrue()
        ->and(PeoplePages::is('/settings/profile'))->toBeTrue()
        ->and(PeoplePages::is('/.env'))->toBeFalse()
        ->and(PeoplePages::is('/wp-login.php'))->toBeFalse()
        ->and(PeoplePages::is('/xmlrpc.php'))->toBeFalse();
});

it('knows a POST to a page people only read comes from a scanner', function (): void {
    expect(PeoplePages::is('/', 'POST'))->toBeFalse()
        ->and(PeoplePages::is('/privacy', 'post'))->toBeFalse()
        ->and(PeoplePages::is('/_astro/app.js', 'PUT'))->toBeFalse()
        ->and(PeoplePages::is('/', 'HEAD'))->toBeTrue()
        ->and(PeoplePages::is('/login', 'POST'))->toBeTrue()
        ->and(PeoplePages::is('/settings/profile', 'PATCH'))->toBeTrue();
});
