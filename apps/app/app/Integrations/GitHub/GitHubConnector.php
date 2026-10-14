<?php

declare(strict_types=1);

namespace App\Integrations\GitHub;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * GitHub's REST API, where Traceway's releases are published. The repository is public,
 * so a token only raises the hourly limit.
 */
final class GitHubConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 3;

    protected int $requestTimeout = 10;

    public function __construct(
        private readonly string $baseUrl = 'https://api.github.com',
        private readonly ?string $token = null,
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        $headers = [
            'User-Agent' => 'Traceway/1.0 (+https://traceway.online)',
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        if ($this->token !== null && $this->token !== '') {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        return $headers;
    }
}
