<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;

#[RequestBody(
    request: 'ImportRouteRequest',
    description: 'A GPX file to plan from.',
    required: true,
    content: new MediaType(
        mediaType: 'multipart/form-data',
        schema: new Schema(
            required: ['file'],
            properties: [
                new Property(property: 'file', description: 'A .gpx file of at most 5 MB.', type: 'string', format: 'binary'),
            ],
            type: 'object',
        ),
    ),
)]
class ImportRouteRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:5120', 'extensions:gpx'],
        ];
    }

    public function contents(): string
    {
        $file = $this->file('file');

        throw_unless($file instanceof UploadedFile, InvalidArgumentException::class, 'A file is required.');

        return (string) $file->get();
    }
}
