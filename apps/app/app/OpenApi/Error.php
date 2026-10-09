<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes\AdditionalProperties;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'Error',
    description: 'A call that did not succeed. A validation failure also lists one message per field.',
    required: ['message'],
    properties: [
        new Property(
            property: 'message',
            type: 'string',
            example: 'The given data was invalid.',
        ),
        new Property(
            property: 'errors',
            description: 'Present when a field failed validation. The key is the field name.',
            type: 'object',
            additionalProperties: new AdditionalProperties(
                type: 'array',
                items: new Items(type: 'string', example: 'The sport field is required.'),
            ),
        ),
    ],
    type: 'object',
)]
final class Error {}
