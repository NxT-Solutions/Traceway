<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare;

use App\Domain\Access\HumanCheck;
use App\Integrations\Cloudflare\Requests\SiteVerifyRequest;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;

final readonly class TurnstileHumanCheck implements HumanCheck
{
    public function __construct(
        private TurnstileConnector $turnstileConnector,
        private string $secret,
    ) {}

    public function passes(string $token, string $ip): bool
    {
        try {
            $response = $this->turnstileConnector->send(new SiteVerifyRequest($this->secret, $token, $ip));
        } catch (FatalRequestException $fatalRequestException) {
            throw new RuntimeException('Turnstile did not answer.', 0, $fatalRequestException);
        }

        throw_if($response->failed(), RuntimeException::class, 'Turnstile did not answer.');

        return $response->json('success') === true;
    }
}
