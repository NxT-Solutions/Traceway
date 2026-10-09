<?php

declare(strict_types=1);

/** @var array{default: string, documentations: array<string, mixed>, defaults: array<string, mixed>} $package */
$package = require base_path('vendor/darkaonline/l5-swagger/config/l5-swagger.php');

unset($package['documentations']['default']);

$package['default'] = 'traceway';
$package['defaults']['generate_always'] = env('APP_ENV') !== 'production';

$package['documentations']['traceway'] = [
    'api' => [
        'title' => 'Traceway',
    ],
    'routes' => [
        'docs' => 'docs/spec',
        'oauth2_callback' => 'docs/oauth2-callback',
        'middleware' => [
            'api' => ['web'],
            'docs' => ['web'],
            'asset' => ['web'],
        ],
    ],
    'paths' => [
        'docs_json' => 'api-docs.json',
        'docs_yaml' => 'api-docs.yaml',
        'annotations' => [
            base_path('app/OpenApi'),
            base_path('app/Http'),
            base_path('app/Queries'),
            base_path('app/Routes'),
        ],
    ],
];

return $package;
