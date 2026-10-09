<?php

declare(strict_types=1);

return [
    'enabled' => env('APP_ENV') === 'local',

    'password' => 'password',

    'riders' => [
        'road' => [
            'name' => 'Road',
            'email' => 'road@traceway.test',
            'description' => 'Paved loops on quiet roads.',
        ],
        'gravel' => [
            'name' => 'Gravel',
            'email' => 'gravel@traceway.test',
            'description' => 'Mixed roads and dirt.',
        ],
        'run' => [
            'name' => 'Run',
            'email' => 'run@traceway.test',
            'description' => 'Paths and pavements.',
        ],
        'hike' => [
            'name' => 'Hike',
            'email' => 'hike@traceway.test',
            'description' => 'Trails and a shorter day.',
        ],
    ],
];
