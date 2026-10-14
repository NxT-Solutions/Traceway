<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Routing\Sport;
use App\Validation\LineWithinReach;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'RouteDetailsRequest',
    description: 'A finished line to describe.',
    required: true,
    content: new JsonContent(
        required: ['sport', 'coordinates'],
        properties: [
            new Property(property: 'sport', type: 'string', enum: ['road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike']),
            new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
        ],
        type: 'object',
    ),
)]
class RouteDetailsRequest extends FormRequest
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
            'sport' => ['required', Rule::enum(Sport::class)],
            'coordinates' => ['required', 'array', 'min:2', 'max:8000', new LineWithinReach],
            'coordinates.*' => ['array', 'size:2'],
            'coordinates.*.0' => ['numeric', 'between:-180,180'],
            'coordinates.*.1' => ['numeric', 'between:-90,90'],
        ];
    }

    public function sport(): Sport
    {
        $sport = $this->validated('sport');

        throw_unless(is_string($sport), InvalidArgumentException::class, 'A sport is required.');

        return Sport::from($sport);
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public function coordinates(): array
    {
        /** @var list<array{0: float|int, 1: float|int}> $coordinates */
        $coordinates = $this->validated('coordinates');
        $points = [];

        foreach ($coordinates as $coordinate) {
            $points[] = [(float) $coordinate[0], (float) $coordinate[1]];
        }

        return $points;
    }
}
