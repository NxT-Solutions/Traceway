<?php

declare(strict_types=1);

namespace App\Integrations\RideWithGps\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Data\MultipartValue;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasMultipartBody;

/**
 * Uploads a GPX file as a new route. Ride with GPS reads it in the background and answers with a task.
 */
final class UploadRouteRequest extends Request implements HasBody
{
    use HasMultipartBody;

    #[Override]
    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $gpx,
        private readonly string $filename,
        private readonly string $name,
        private readonly string $description,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/v1/routes.json';
    }

    /**
     * @return list<MultipartValue>
     */
    protected function defaultBody(): array
    {
        return [
            new MultipartValue('file', $this->gpx, $this->filename, ['Content-Type' => 'application/gpx+xml']),
            new MultipartValue('name', $this->name),
            new MultipartValue('description', $this->description),
        ];
    }
}
