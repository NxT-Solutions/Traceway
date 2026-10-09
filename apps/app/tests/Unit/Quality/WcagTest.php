<?php

declare(strict_types=1);

/**
 * WCAG 2.2 AA checks that can be decided from the stylesheets.
 * A passing run is the token contract. It is not a conformance claim.
 */
uses()->group('a11y');

it('keeps text at 4.5 to 1 and the focus ring at 3 to 1', function (): void {
    $root = dirname(__DIR__, 5);

    $paperPairs = [
        ['ink', 'paper', 4.5],
        ['ink', 'card', 4.5],
        ['muted', 'paper', 4.5],
        ['chartreuse-ink', 'chartreuse', 4.5],
        ['chart-ink', 'chart', 4.5],
        ['chart-ink', 'chartreuse', 4.5],
        ['muted-on-ink', 'ink', 4.5],
        ['paper-on-ink', 'ink', 4.5],
    ];

    foreach ([
        $root.'/apps/web/src/styles/global.css' => ':root',
        $root.'/apps/web/src/styles/v2.css' => ':root',
        $root.'/apps/app/resources/css/login-scene.css' => '.tw-login',
    ] as $file => $selector) {
        $tokens = oklchTokens(cssBlock(file_get_contents($file) ?: '', $selector));

        foreach ($paperPairs as [$foreground, $background, $minimum]) {
            if (! isset($tokens[$foreground], $tokens[$background])) {
                continue;
            }

            $ratio = contrastRatio($tokens[$foreground], $tokens[$background]);

            expect($ratio)->toBeGreaterThanOrEqual($minimum);
        }
    }

    $app = file_get_contents($root.'/apps/app/resources/css/app.css') ?: '';

    foreach ([':root', '.dark'] as $selector) {
        $tokens = oklchTokens(cssBlock($app, $selector));
        $textPairs = [
            ['foreground', 'background', 4.5],
            ['card-foreground', 'card', 4.5],
            ['muted-foreground', 'background', 4.5],
            ['muted-foreground', 'muted', 4.5],
            ['primary-foreground', 'primary', 4.5],
            ['destructive-foreground', 'destructive', 4.5],
            ['ring', 'background', 3.0],
            ['ring', 'card', 3.0],
        ];

        foreach ($textPairs as [$foreground, $background, $minimum]) {
            $ratio = contrastRatio($tokens[$foreground], $tokens[$background]);

            expect($ratio)->toBeGreaterThanOrEqual($minimum);
        }
    }

    $canonical = oklchTokens(cssBlock(file_get_contents($root.'/apps/web/src/styles/global.css') ?: '', ':root'));

    expect(contrastRatio($canonical['ink'], $canonical['paper']))->toBeGreaterThan(15);
});

it('replaces a removed outline with a visible ring', function (): void {
    $root = dirname(__DIR__, 5);
    $files = array_merge(
        glob($root.'/apps/app/resources/css/*.css') ?: [],
        glob($root.'/apps/web/src/styles/*.css') ?: [],
    );

    foreach ($files as $file) {
        $css = file_get_contents($file) ?: '';
        $blocks = preg_split('/}/', $css) ?: [];

        foreach ($blocks as $block) {
            if (preg_match('/outline\s*:\s*none/', $block) !== 1) {
                continue;
            }

            expect($block)->toContain('box-shadow');
        }
    }
});

it('snaps motion when the visitor asks for less', function (): void {
    $root = dirname(__DIR__, 5);

    foreach ([
        $root.'/apps/app/resources/css/app.css',
        $root.'/apps/app/resources/css/planner.css',
        $root.'/apps/app/resources/css/login-scene.css',
        $root.'/apps/app/resources/css/settings-scene.css',
        $root.'/apps/web/src/styles/global.css',
        $root.'/apps/web/src/styles/v2.css',
    ] as $file) {
        expect(file_get_contents($file) ?: '')->toContain('prefers-reduced-motion');
    }
});

it('does not load type from a third party', function (): void {
    $root = dirname(__DIR__, 5);
    $files = array_merge(
        glob($root.'/apps/app/resources/css/*.css') ?: [],
        glob($root.'/apps/web/src/styles/*.css') ?: [],
        glob($root.'/apps/web/src/layouts/*.astro') ?: [],
        [$root.'/apps/app/resources/views/app.blade.php'],
    );

    foreach ($files as $file) {
        $css = file_get_contents($file) ?: '';

        expect($css)->not->toContain('fonts.googleapis.com')
            ->and($css)->not->toContain('fonts.gstatic.com')
            ->and($css)->not->toContain('use.typekit.net');
    }
});

/**
 * @return array<string, array{0: float, 1: float, 2: float}>
 */
function oklchTokens(string $block): array
{
    preg_match_all('/--([\w-]+)\s*:\s*oklch\(\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s*\)/', $block, $matches, PREG_SET_ORDER);
    $tokens = [];

    foreach ($matches as $match) {
        $tokens[$match[1]] = [(float) $match[2], (float) $match[3], (float) $match[4]];
    }

    return $tokens;
}

function cssBlock(string $css, string $selector): string
{
    $pattern = '/'.preg_quote($selector, '/').'\s*\{/';

    if (preg_match($pattern, $css, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }

    $start = $match[0][1] + strlen($match[0][0]);
    $depth = 1;
    $length = strlen($css);

    for ($index = $start; $index < $length; $index++) {
        if ($css[$index] === '{') {
            $depth++;
        }

        if ($css[$index] === '}') {
            $depth--;

            if ($depth === 0) {
                return substr($css, $start, $index - $start);
            }
        }
    }

    return '';
}

/**
 * @param  array{0: float, 1: float, 2: float}  $foreground
 * @param  array{0: float, 1: float, 2: float}  $background
 */
function contrastRatio(array $foreground, array $background): float
{
    $lighter = max(relativeLuminance($foreground), relativeLuminance($background));
    $darker = min(relativeLuminance($foreground), relativeLuminance($background));

    return ($lighter + 0.05) / ($darker + 0.05);
}

/**
 * @param  array{0: float, 1: float, 2: float}  $oklch
 */
function relativeLuminance(array $oklch): float
{
    [$lightness, $chroma, $hue] = $oklch;
    $radians = deg2rad($hue);
    $a = $chroma * cos($radians);
    $b = $chroma * sin($radians);
    $long = $lightness + (0.3963377774 * $a) + (0.2158037573 * $b);
    $medium = $lightness - (0.1055613458 * $a) - (0.0638541728 * $b);
    $short = $lightness - (0.0894841775 * $a) - (1.2914855480 * $b);
    $long **= 3;
    $medium **= 3;
    $short **= 3;
    $red = max(0.0, min(1.0, (4.0767416621 * $long) - (3.3077115913 * $medium) + (0.2309699292 * $short)));
    $green = max(0.0, min(1.0, (-1.2684380046 * $long) + (2.6097574011 * $medium) - (0.3413193965 * $short)));
    $blue = max(0.0, min(1.0, (-0.0041960863 * $long) - (0.7034186147 * $medium) + (1.7076147010 * $short)));

    return (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);
}
