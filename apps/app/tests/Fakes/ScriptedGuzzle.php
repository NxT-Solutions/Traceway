<?php

declare(strict_types=1);

namespace Tests\Fakes;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * A Guzzle client for a sign-in service's provider that answers from a script, in turn, and remembers each
 * request it was sent. A request past the end of the script fails.
 */
final class ScriptedGuzzle
{
    /** @var array<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<array-key, mixed>}> */
    private array $history = [];

    private readonly Client $client;

    /**
     * @param  list<ResponseInterface|Throwable>  $answers
     */
    public function __construct(array $answers)
    {
        $handlerStack = HandlerStack::create(new MockHandler($answers));
        $handlerStack->push(Middleware::history($this->history));

        $this->client = new Client(['handler' => $handlerStack]);
    }

    public function client(): Client
    {
        return $this->client;
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return array_values(array_map(static fn (array $entry): RequestInterface => $entry['request'], $this->history));
    }

    public function request(int $index): RequestInterface
    {
        return $this->requests()[$index] ?? throw new RuntimeException('The client was sent no request '.$index.'.');
    }
}
