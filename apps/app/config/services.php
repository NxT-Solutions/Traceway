<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'router' => env('ROUTER_DRIVER', 'valhalla'),

    'graphhopper' => [
        'url' => env('GRAPHHOPPER_URL', 'http://graphhopper:8989'),
    ],

    'osrm' => [
        'url' => env('OSRM_URL', 'https://router.project-osrm.org'),
    ],

    // Place search for AI tools that name a start instead of dropping a pin.
    'nominatim' => [
        'url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
    ],

    // Water, toilets, and cafés along a route, read from the OpenStreetMap
    // map tiles the planner's map draws.
    'openfreemap' => [
        'url' => env('OPENFREEMAP_URL', 'https://tiles.openfreemap.org'),
    ],

    // Services a rider connects so Traceway can send a route to their device.
    // A service without a client id stays hidden.
    'wahoo' => [
        'client_id' => env('WAHOO_CLIENT_ID'),
        'client_secret' => env('WAHOO_CLIENT_SECRET'),
        'url' => env('WAHOO_URL', 'https://api.wahooligan.com'),
    ],

    'ride_with_gps' => [
        'client_id' => env('RIDEWITHGPS_CLIENT_ID'),
        'client_secret' => env('RIDEWITHGPS_CLIENT_SECRET'),
        'api_key' => env('RIDEWITHGPS_API_KEY'),
        'url' => env('RIDEWITHGPS_URL', 'https://ridewithgps.com'),
    ],

    'valhalla' => [
        'url' => env('VALHALLA_URL', 'https://valhalla1.openstreetmap.de'),
        // The public server refuses a request whose points span more than
        // 150 km, so longer routes go out in pieces under this span.
        'max_request_metres' => (int) env('VALHALLA_MAX_REQUEST_METRES', 140000),
        // The public server takes at most ten points in one request.
        'max_request_points' => (int) env('VALHALLA_MAX_REQUEST_POINTS', 10),
    ],

];
