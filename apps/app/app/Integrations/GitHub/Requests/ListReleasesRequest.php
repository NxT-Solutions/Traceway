<?php

declare(strict_types=1);

namespace App\Integrations\GitHub\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

final class ListReleasesRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  string  $repository  The owner and name, such as NxT-Solutions/Traceway.
     */
    public function __construct(private readonly string $repository) {}

    public function resolveEndpoint(): string
    {
        return '/repos/'.$this->repository.'/releases';
    }

    /**
     * @return array<string, int>
     */
    protected function defaultQuery(): array
    {
        return [
            'per_page' => 30,
        ];
    }
}
