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
    request: 'StoreRouteImageRequest',
    description: 'The picture a shared link shows: the route on the map.',
    required: true,
    content: new MediaType(
        mediaType: 'multipart/form-data',
        schema: new Schema(
            required: ['image'],
            properties: [
                new Property(property: 'image', description: 'A JPEG of 1200 by 630, at most 1 MB.', type: 'string', format: 'binary'),
            ],
            type: 'object',
        ),
    ),
)]
class StoreRouteImageRequest extends FormRequest
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
            'image' => ['required', 'file', 'mimetypes:image/jpeg', 'max:1024', 'dimensions:width=1200,height=630'],
        ];
    }

    public function jpeg(): string
    {
        $file = $this->file('image');

        throw_unless($file instanceof UploadedFile, InvalidArgumentException::class, 'A picture is required.');

        return (string) $file->get();
    }
}
