<?php

declare(strict_types=1);

return [
    'enabled' => env('APP_ENV') === 'local',

    'password' => 'password',

    // One example of each role, so the admin area can be tried with a click: Road is a Super Admin, Gravel an
    // Admin, and Run and Hike are riders.
    'riders' => [
        'road' => [
            'name' => 'Road',
            'email' => 'road@traceway.test',
            'description' => 'Paved loops on quiet roads.',
            'role' => 'super-admin',
        ],
        'gravel' => [
            'name' => 'Gravel',
            'email' => 'gravel@traceway.test',
            'description' => 'Mixed roads and dirt.',
            'role' => 'admin',
        ],
        'run' => [
            'name' => 'Run',
            'email' => 'run@traceway.test',
            'description' => 'Paths and pavements.',
            'role' => 'user',
        ],
        'hike' => [
            'name' => 'Hike',
            'email' => 'hike@traceway.test',
            'description' => 'Trails and a shorter day.',
            'role' => 'user',
        ],
    ],
];
