{{--
    Every error page, branded. It loads nothing from Vite and touches no database,
    so it still renders when the app is broken. The words mirror apps/web/src/errors.ts.
--}}
@php
    $home = ['label' => 'Back to the start', 'href' => '/'];
    $planner = ['label' => 'Open the planner', 'href' => '/dashboard'];
    $retry = ['label' => 'Try again', 'href' => url()->full()];

    $pages = [
        400 => [
            'Bad request',
            'That request got tangled',
            'Something in it did not add up. Go back and try again.',
            'run',
            'bang',
        ],
        401 => [
            'Sign in',
            'Sign in to keep going',
            'This page is for signed-in Traceway users.',
            'hike',
            'key',
            ['label' => 'Sign in', 'href' => '/login'],
            $home,
        ],
        403 => [
            'No entry',
            'Private trail',
            'You cannot open this page. If someone shared it with you, the link may have expired.',
            'ride',
            'lock',
        ],
        404 => [
            'Not found',
            'Off the map',
            'We cannot find that page. The link may be old, or the address has a typo.',
            'run',
            'question',
        ],
        405 => [
            'Wrong way',
            'Wrong way round',
            'That page cannot be opened like that. Go back and try again.',
            'run',
            'bang',
        ],
        408 => [
            'Timed out',
            'You took a breather',
            'The page waited too long and gave up. Try again.',
            'hike',
            'hourglass',
            $retry,
            $home,
        ],
        413 => [
            'Too heavy',
            'That is a heavy load',
            'That upload is too big to carry. Try a smaller file.',
            'hike',
            'weight',
        ],
        414 => [
            'Too long',
            'That link is too long',
            'The address is longer than we can read. Try a shorter link.',
            'run',
            'bang',
        ],
        419 => [
            'Expired',
            'This page went stale',
            'You were away a while, so the page timed out. Open it again and carry on.',
            'hike',
            'hourglass',
            ['label' => 'Back to the planner', 'href' => '/dashboard'],
            $home,
        ],
        429 => [
            'Slow down',
            'Easy on the pace',
            'That was a lot in a short time. Catch your breath and try again in a minute.',
            'run',
            'stopwatch',
            $retry,
            $home,
        ],
        500 => [
            'Server error',
            'We hit a pothole',
            'Something broke on our side. Try again in a minute.',
            'ride',
            'wrench',
            $retry,
            $home,
        ],
        502 => [
            'Bad gateway',
            'Dropped the chain',
            'Our server did not answer. Try again in a minute.',
            'ride',
            'wrench',
            $retry,
            $home,
        ],
        503 => [
            'Back soon',
            'At the aid station',
            'Traceway is getting a quick tune-up. Back in a few minutes.',
            'run',
            'bottle',
            $retry,
            $home,
        ],
        504 => [
            'Timed out',
            'Still climbing',
            'Our server is taking too long to answer. Try again in a minute.',
            'ride',
            'hourglass',
            $retry,
            $home,
        ],
    ];
    $clientError = [
        'Error',
        'That did not work',
        'Something about that request was off. Go back and try again.',
        'run',
        'bang',
    ];

    $entry = $pages[$status] ?? ($status >= 500 ? $pages[500] : $clientError);
    $page = [
        'label' => $entry[0],
        'title' => $entry[1],
        'message' => $entry[2],
        'athlete' => $entry[3],
        'prop' => $entry[4],
        'primary' => $entry[5] ?? $home,
        'secondary' => $entry[6] ?? $planner,
        'jolt' => in_array($status, [500, 502], true) || ($status >= 500 && !isset($pages[$status])),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0b2224">
    <title>{{ $page['title'] }} · Traceway</title>
    {{-- Inertia shows an error response in a frame. Links leave the frame. --}}
    <base target="_top">
    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
    @fonts
    <style>
        {!! file_get_contents(resource_path('css/errors.css')) !!}
    </style>
</head>

<body class="is-{{ $page['athlete'] }} @if ($page['jolt']) jolt @endif">
    <header class="err-top">
        <a class="err-brand" href="/" aria-label="Traceway home">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <path d="M6 23 V12 a6 6 0 0 1 6 -6 h4 a6 6 0 0 1 6 6 v3" stroke="currentColor" stroke-width="3"
                    stroke-linecap="round" stroke-linejoin="round"></path>
                <circle class="mkd" cx="22" cy="22" r="3" fill="currentColor"></circle>
            </svg>
            <span>Traceway</span>
        </a>
    </header>

    <main class="err-main">
        <div class="bib" aria-hidden="true">
            <span class="bib-band"><span>Traceway</span><span>Bib</span></span>
            <span class="bib-code">{{ $status }}</span>
            <span class="bib-label">{{ $page['label'] }}</span>
            <i></i><i></i><i></i><i></i>
        </div>
        <h1>{{ $page['title'] }}</h1>
        <p>{{ $page['message'] }}</p>
        <div class="err-actions">
            <a class="err-fill" href="{{ $page['primary']['href'] }}">{{ $page['primary']['label'] }}</a>
            <a class="err-ghost" href="{{ $page['secondary']['href'] }}">{{ $page['secondary']['label'] }}</a>
        </div>
    </main>

    @include('errors.scene')
</body>

</html>
