<?php

declare(strict_types=1);

use App\Mail\TracewayMail;

dataset('templates', ['verify-email', 'welcome', 'reset-password', 'password-changed', 'email-changed', 'account-deleted', 'data-export', 'connection-added', 'connection-removed', 'connection-sign-up', 'confirm-new-email']);

$values = [
    'name' => 'Road & Co',
    'email' => 'road@traceway.test',
    'new_email' => 'new@traceway.test',
    'service' => 'Ride & Go',
    'url' => 'https://traceway.test/link?a=1&b=2',
    'reset' => 'https://traceway.test/reset?a=1&b=2',
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

test('a connection message names the service and links to the password reset', function (string $template) use ($values): void {
    expect(TracewayMail::html($template, $values))
        ->toContain('Ride &amp; Go')
        ->toContain('href="https://traceway.test/reset?a=1&amp;b=2"')
        ->and(TracewayMail::text($template, $values))
        ->toContain('Ride & Go')
        ->toContain('reset your password: https://traceway.test/reset?a=1&b=2');
})->with(['connection-added', 'connection-removed']);

test('the sign-up link names the service and says no account is opened without it', function () use ($values): void {
    expect(TracewayMail::html('connection-sign-up', $values))
        ->toContain('To finish opening your Traceway account with Ride &amp; Go, confirm this is your email.')
        ->toContain('Finish your account</a>')
        ->and(TracewayMail::text('connection-sign-up', $values))
        ->toContain('Finish your account: https://traceway.test/link?a=1&b=2')
        ->toContain('If you did not ask for this, ignore this email. No account is opened.');
});
