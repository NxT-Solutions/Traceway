<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;

#[RequestBody(
    request: 'AvoidStretchRequest',
    description: 'A tapped point, and the sport and surface used to find the way under it.',
    required: true,
    content: new JsonContent(
        required: ['latitude', 'longitude', 'sport'],
        properties: [
            new Property(property: 'latitude', type: 'number', maximum: 90, minimum: -90),
            new Property(property: 'longitude', type: 'number', maximum: 180, minimum: -180),
            new Property(property: 'sport', type: 'string', enum: ['road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike']),
            new Property(property: 'surface', description: "The route's surface. Without it, the sport's own.", type: 'string', enum: ['paved', 'mixed', 'dirt']),
        ],
        type: 'object',
    ),
)]
class AvoidStretchRequest extends FormRequest
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'sport' => ['required', Rule::enum(Sport::class)],
            'surface' => ['nullable', Rule::enum(Surface::class)],
        ];
    }

    public function sport(): Sport
    {
        return Sport::from($this->string('sport')->toString());
    }

    public function surface(): Surface
    {
        return Surface::tryFrom($this->string('surface')->toString()) ?? $this->sport()->defaultSurface();
    }
}
