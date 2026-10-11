<?php

declare(strict_types=1);

return [
    'domain' => null,
    'path' => 'docs',
    'middleware' => ['web'],
    'url' => '/docs/spec',
    'content' => null,
    'file' => null,
    'sources' => [],
    'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
    'configuration' => [
        'theme' => 'default',
        'layout' => 'modern',
        'proxyUrl' => null,
        'showSidebar' => true,
        'hideModels' => false,
        'documentDownloadType' => 'json',
        'hideTestRequestButton' => false,
        'hideSearch' => false,
        // No darkMode or forceDarkModeState: the reference follows the device
        // until the reader flips the toggle, like the planner does.
        'hideDarkModeToggle' => false,
        'withDefaultFonts' => true,
        'telemetry' => false,
        'showDeveloperTools' => 'never',
        'metaData' => [
            'title' => 'Traceway',
        ],
    ],
];
