<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
    <script nonce="{{ Vite::cspNonce() }}">
        (function() {
            const appearance = '{{ $appearance ?? 'system' }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    {{-- Inline style to set the HTML background color based on our theme in app.css --}}
    <style>
        html {
            background-color: oklch(0.97 0.012 95);
        }

        html.dark {
            background-color: oklch(0.18 0.03 200);
        }
    </style>

    @isset($sharePreview)
        {{-- A shared route is private: chats show the preview, search engines leave it out. --}}
        <meta name="robots" content="noindex, nofollow">
        <meta name="description" content="{{ $sharePreview['description'] }}">
        <meta name="theme-color" content="#071f20">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Traceway">
        <meta property="og:title" content="{{ $sharePreview['title'] }}">
        <meta property="og:description" content="{{ $sharePreview['description'] }}">
        <meta property="og:url" content="{{ $sharePreview['url'] }}">
        <meta property="og:image" content="{{ $sharePreview['image'] }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $sharePreview['image_alt'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $sharePreview['title'] }}">
        <meta name="twitter:description" content="{{ $sharePreview['description'] }}">
        <meta name="twitter:image" content="{{ $sharePreview['image'] }}">
        <meta name="twitter:image:alt" content="{{ $sharePreview['image_alt'] }}">
    @else
        <meta name="description" content="Plan a loop for any sport and send it to your watch, GPS, or bike computer.">
    @endisset

    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

    @fonts

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
    <x-inertia::head>
        <title>{{ $sharePreview['title'] ?? config('app.name', 'Laravel') }}</title>
    </x-inertia::head>
</head>

<body class="font-sans antialiased">
    <x-inertia::app />
</body>

</html>
