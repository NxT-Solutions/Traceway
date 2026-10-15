<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'SendRouteToServiceRequest',
    description: 'Which part of the route to send. Leave out the leg for a single-sport route.',
    required: false,
    content: new JsonContent(
        properties: [
            new Property(property: 'leg', description: 'A land leg of a multisport plan, counted from 1.', type: 'integer', maximum: 5, minimum: 1),
        ],
        type: 'object',
    ),
)]
class SendRouteToServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'leg' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function leg(): int
    {
        $leg = $this->validated('leg');

        return is_int($leg) ? $leg : (is_numeric($leg) ? (int) $leg : 0);
    }
}
