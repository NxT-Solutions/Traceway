<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes\Get;
use OpenApi\Attributes\Info;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\OpenApi;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes\SecurityScheme;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[Get(
    path: '/up',
    operationId: 'health',
    summary: 'Application health',
    security: [],
    tags: ['Health'],
)]
#[Response(
    response: HttpResponse::HTTP_OK,
    description: 'The application is up.',
)]
#[OpenApi(
    info: new Info(
        version: '1.0.0',
        description: "One API. A call returns the signed-in person's own data, or a course opened with a signed link. There is no list of other people. A password, two-factor secret, recovery code, or remember token is never returned.",
        title: 'Traceway',
    ),
    security: [['session' => []]],
)]
#[SecurityScheme(
    securityScheme: 'session',
    type: 'apiKey',
    description: 'The session cookie of the signed-in person. State-changing calls also send the X-XSRF-TOKEN header. A signed share link does not use this cookie.',
    name: 'laravel_session',
    in: 'cookie',
)]
#[SecurityScheme(
    securityScheme: 'deviceToken',
    type: 'http',
    description: "The token a device app, such as Traceway for Connect IQ on a Garmin, gets after the rider types its pairing code in Settings, then Devices. It reads only that rider's own routes.",
    scheme: 'bearer',
)]
#[Schema(
    schema: 'DeviceRouteLine',
    required: ['line'],
    properties: [
        new Property(
            property: 'line',
            description: 'Latitude then longitude, in degrees.',
            type: 'array',
            items: new Items(type: 'array', items: new Items(type: 'number'), maxItems: 2, minItems: 2),
            maxItems: 150,
        ),
    ],
)]
final class Spec {}
