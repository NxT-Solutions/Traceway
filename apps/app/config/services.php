<?php

declare(strict_types=1);

/*
| The HTTP client each sign-in service gets: short timeouts, so a slow service fails the rider's request
| instead of holding a worker, and a User-Agent that says who is asking, as OpenStreetMap's policy requires.
*/
$signIn = [
    'timeout' => 10,
    'connect_timeout' => 5,
    'headers' => ['User-Agent' => 'Traceway (+'.rtrim((string) env('APP_URL', 'https://traceway.online'), '/').')'],
];

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

    /*
    | Accounts a rider logs in with or connects, under Settings, then Connections. Each key is the Socialite
    | driver. A service without its keys stays hidden, so each can go live on its own. The redirect is the
    | callback registered at the service; a path is resolved against the site's own address.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        'guzzle' => $signIn,
    ],

    // Sign in with Apple signs its own client secret with the private key for every request, so there is none
    // to set. The client id is the Services ID, not an app's bundle id. The key is the .p8 file's text.
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => null,
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'redirect' => env('APPLE_REDIRECT_URI', '/auth/apple/callback'),
        'guzzle' => $signIn,
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
        'guzzle' => $signIn,
    ],

    // Instagram's own login, for business and creator accounts. Its app id and secret are not Facebook's.
    'instagram' => [
        'client_id' => env('INSTAGRAM_CLIENT_ID'),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
        'redirect' => env('INSTAGRAM_REDIRECT_URI', '/auth/instagram/callback'),
        'guzzle' => $signIn,
    ],

    // The OAuth 2.0 client id and secret, not the API key.
    'x' => [
        'client_id' => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
        'redirect' => env('X_REDIRECT_URI', '/auth/x/callback'),
        'guzzle' => $signIn,
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', '/auth/github/callback'),
        'guzzle' => $signIn,
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI', '/auth/discord/callback'),
        'guzzle' => $signIn,
    ],

    // Garmin's developer program, with OAuth 2.0 and PKCE. The client id is the consumer key.
    'garmin_connect' => [
        'client_id' => env('GARMIN_CONNECT_CLIENT_ID'),
        'client_secret' => env('GARMIN_CONNECT_CLIENT_SECRET'),
        'redirect' => env('GARMIN_CONNECT_REDIRECT_URI', '/auth/garmin_connect/callback'),
        'guzzle' => $signIn,
    ],

    // Wahoo and Ride with GPS also take the routes Send to device sends. Their callbacks stay where they were
    // first registered at each service.
    'wahoo' => [
        'client_id' => env('WAHOO_CLIENT_ID'),
        'client_secret' => env('WAHOO_CLIENT_SECRET'),
        'redirect' => env('WAHOO_REDIRECT_URI', '/settings/services/wahoo/callback'),
        'url' => env('WAHOO_URL', 'https://api.wahooligan.com'),
        // Turn on once the email permission is enabled on the Wahoo developer app. Until then Wahoo riders type
        // their email when they open an account.
        'request_email' => (bool) env('WAHOO_REQUEST_EMAIL', default: false),
        'guzzle' => $signIn,
    ],

    'ride_with_gps' => [
        'client_id' => env('RIDEWITHGPS_CLIENT_ID'),
        'client_secret' => env('RIDEWITHGPS_CLIENT_SECRET'),
        'api_key' => env('RIDEWITHGPS_API_KEY'),
        'redirect' => env('RIDEWITHGPS_REDIRECT_URI', '/settings/services/ride_with_gps/callback'),
        'url' => env('RIDEWITHGPS_URL', 'https://ridewithgps.com'),
        'guzzle' => $signIn,
    ],

    // "prod" for runsignup.com, "test" for test.runsignup.com, which has its own clients.
    'runsignup' => [
        'client_id' => env('RUNSIGNUP_CLIENT_ID'),
        'client_secret' => env('RUNSIGNUP_CLIENT_SECRET'),
        'redirect' => env('RUNSIGNUP_REDIRECT_URI', '/auth/runsignup/callback'),
        'environment' => env('RUNSIGNUP_ENVIRONMENT', 'prod'),
        'guzzle' => $signIn,
    ],

    'openstreetmap' => [
        'client_id' => env('OPENSTREETMAP_CLIENT_ID'),
        'client_secret' => env('OPENSTREETMAP_CLIENT_SECRET'),
        'redirect' => env('OPENSTREETMAP_REDIRECT_URI', '/auth/openstreetmap/callback'),
        'guzzle' => $signIn,
    ],

    // The admin area's Traffic page. A read-only Analytics token, not the token the deploy purges the cache with.
    'cloudflare_analytics' => [
        'token' => env('CLOUDFLARE_ANALYTICS_TOKEN'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'site_tag' => env('CLOUDFLARE_WEB_ANALYTICS_SITE_TAG'),
        // The R2 bucket Litestream writes the database backups to, for the admin area's Backups page.
        'backup_bucket' => env('CLOUDFLARE_R2_BACKUP_BUCKET', 'traceway-backups'),
    ],

    // Cloudflare Turnstile under the sign-up and password-reset forms. Both
    // keys empty turns it off.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'valhalla' => [
        'url' => env('VALHALLA_URL', 'https://valhalla1.openstreetmap.de'),
        // The public server refuses a request whose points span more than
        // 150 km, so longer routes go out in pieces under this span.
        'max_request_metres' => (int) env('VALHALLA_MAX_REQUEST_METRES', 140000),
        // The public server takes at most ten points in one request.
        'max_request_points' => (int) env('VALHALLA_MAX_REQUEST_POINTS', 10),
    ],

    // The Garmin app's listing in the Connect IQ store. The status page asks the
    // store whether riders can find it there.
    'connect_iq' => [
        'url' => env('CONNECT_IQ_STORE_URL', 'https://apps.garmin.com'),
        'store_id' => env('CONNECT_IQ_STORE_ID', 'a0c32e89-323f-4522-9ee8-c6af6cce14c3'),
    ],

];
