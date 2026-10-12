<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <script>
        (function() {
            const appearance = '{{ $appearance ?? 'system' }}';

            if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <style>
        html {
            background-color: oklch(0.97 0.012 95);
        }

        html.dark {
            background-color: oklch(0.18 0.03 200);
        }
    </style>

    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

    @fonts

    @vite(['resources/css/app.css'])

    <title>Connect {{ $client->name }} · Traceway</title>
</head>

@php
    $returnHost = parse_url((string) ($client->redirect_uris[0] ?? ''), PHP_URL_HOST);
@endphp

<body class="bg-background text-foreground font-sans antialiased">
    <main class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <a href="/" class="text-foreground mb-8 flex items-center gap-2 text-lg font-semibold no-underline">
                <svg viewBox="0 0 32 32" fill="none" class="size-8" aria-hidden="true">
                    <path d="M8.2 25.2 V15.4 a5.1 5.1 0 0 1 5.1 -5.1 h3.4 a5.1 5.1 0 0 1 5.1 5.1 v2.3"
                        stroke="currentColor" stroke-width="2.7" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="22.1" cy="23.5" r="2.45" fill="#97cb46" />
                </svg>
                Traceway
            </a>

            <div class="border-border bg-card text-card-foreground rounded-[20px] border p-6">
                <h1 class="text-[22px] font-semibold leading-tight">
                    Let {{ $client->name }} plan routes for you?
                </h1>

                <p class="text-muted-foreground mt-3 text-[15px]">
                    {{ $client->name }} will be able to:
                </p>

                <ul class="mt-2 space-y-2 text-[15px]">
                    <li class="flex gap-2"><span aria-hidden="true"
                            class="bg-primary mt-2 size-1.5 shrink-0 rounded-full"></span>Plan loops and multisport
                        sessions</li>
                    <li class="flex gap-2"><span aria-hidden="true"
                            class="bg-primary mt-2 size-1.5 shrink-0 rounded-full"></span>Save them to your library, and
                        list what is there</li>
                </ul>

                <p class="text-muted-foreground mt-4 text-[13px]">
                    It cannot see your password or change your account. You are signed in as {{ $user->email }}.
                    @if ($returnHost)
                        After this you go back to <strong
                            class="text-foreground font-medium">{{ $returnHost }}</strong>.
                    @endif
                </p>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row">
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit"
                            class="border-border bg-background hover:bg-accent focus-visible:ring-ring inline-flex h-11 w-full items-center justify-center rounded-[10px] border px-4 text-[15px] font-medium transition-colors focus-visible:outline-none focus-visible:ring-2">
                            Not now
                        </button>
                    </form>

                    <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit"
                            class="bg-primary text-primary-foreground focus-visible:ring-ring inline-flex h-11 w-full items-center justify-center rounded-[10px] px-4 text-[15px] font-semibold transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2">
                            Allow
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-muted-foreground mt-4 text-center text-[13px]">
                You can disconnect it at any time in your settings.
            </p>
        </div>
    </main>
</body>

</html>
