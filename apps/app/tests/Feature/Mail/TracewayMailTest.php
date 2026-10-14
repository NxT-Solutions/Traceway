<?php

declare(strict_types=1);

use App\Mail\TracewayMail;

dataset('templates', ['verify-email', 'welcome', 'reset-password', 'password-changed', 'email-changed', 'account-deleted', 'data-export']);

$values = [
    'name' => 'Road & Co',
    'email' => 'road@traceway.test',
    'new_email' => 'new@traceway.test',
    'url' => 'https://traceway.test/link?a=1&b=2',
    'home' => 'https://traceway.test',
];

test('every template fills its placeholders and escapes the html part', function (string $template) use ($values): void {
    $html = TracewayMail::html($template, $values);

    expect($html)
        ->not->toContain('%%')
        ->toContain('Road &amp; Co');

    if ($template !== 'account-deleted') {
        expect($html)->toContain('https://traceway.test/link?a=1&amp;b=2');
    }
})->with('templates');

test('every template supports dark mode and keeps its colours inline for Outlook', function (string $template) use ($values): void {
    $html = TracewayMail::html($template, $values);

    expect($html)
        ->toContain('<meta name="color-scheme" content="light dark">')
        ->toContain('@media (prefers-color-scheme: dark)')
        ->toContain('[data-ogsc]')
        ->toMatch('/class="tw-card"[^>]*style="[^"]*background-color: #fffcf6/')
        ->toMatch('/class="tw-p"[^>]*style="[^"]*color: #1d3338/');
})->with('templates');

test('every template has a readable plain text part with real links', function (string $template) use ($values): void {
    $text = TracewayMail::text($template, $values);

    expect($text)
        ->not->toMatch('/<[a-z\/!]/i')
        ->not->toContain('%%')
        ->toContain('Hello Road & Co.')
        ->toContain('road@traceway.test');

    if ($template !== 'account-deleted') {
        expect($text)->toContain('https://traceway.test/link?a=1&b=2');
    }
})->with('templates');

test('every template ends with the privacy policy and the terms', function (string $template) use ($values): void {
    expect(TracewayMail::html($template, $values))
        ->toContain('href="'.url('/privacy').'"')
        ->toContain('href="'.url('/terms').'"')
        ->and(TracewayMail::text($template, $values))
        ->toContain('Privacy policy: '.url('/privacy'))
        ->toContain('Terms of use: '.url('/terms'));
})->with('templates');
