<?php

declare(strict_types=1);

return [
    'enabled' => env('APP_ENV') === 'local',

    'password' => 'password',

    'riders' => [
        'road' => [
            'name' => 'Road rider',
            'email' => 'road@traceway.test',
            'description' => 'Paved loops. The usual ride.',
        ],
        'gravel' => [
            'name' => 'Gravel rider',
            'email' => 'gravel@traceway.test',
            'description' => 'Mixed roads and dirt.',
        ],
        'run' => [
            'name' => 'Runner',
            'email' => 'run@traceway.test',
            'description' => 'Footpaths and pavements.',
        ],
        'hike' => [
            'name' => 'Hiker',
            'email' => 'hike@traceway.test',
            'description' => 'Trails and hills.',
        ],
    ],
];
