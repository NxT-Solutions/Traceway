<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedRouteRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:80'],
            'sport' => ['required', Rule::enum(Sport::class)],
            'surface' => ['required', Rule::enum(Surface::class)],
            'elevation' => ['required', Rule::enum(ElevationBias::class)],
            'roads' => ['required', Rule::in(['quiet', 'any'])],
            'distance_metres' => ['required', 'integer', 'min:0', 'max:500000'],
            'ascent_metres' => ['required', 'integer', 'min:0', 'max:20000'],
            ...$this->lineRules('coordinates', required: true),
            ...$this->lineRules('user_coordinates', required: false),
            ...$this->lineRules('finish_coordinates', required: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineRules(string $key, bool $required): array
    {
        $presence = $required ? ['required', 'array', 'min:2'] : ['sometimes', 'nullable', 'array'];

        return [
            $key => [...$presence, 'max:20000'],
            $key.'.*' => ['array', 'size:2'],
            $key.'.*.0' => ['numeric', 'between:-180,180'],
            $key.'.*.1' => ['numeric', 'between:-90,90'],
        ];
    }
}
