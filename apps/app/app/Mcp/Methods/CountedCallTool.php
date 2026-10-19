<?php

declare(strict_types=1);

namespace App\Mcp\Methods;

use App\Contracts\Usage\UsageCounter;
use App\Domain\Usage\UsageMetric;
use Generator;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Override;
use Throwable;

/**
 * Calls a tool, then adds one to today's total for that tool when the call worked, as CountUsage does for a
 * request. The total names the tool and the day, never who called it. Counting never stops the call.
 */
final class CountedCallTool extends CallTool
{
    public function __construct(private readonly UsageCounter $usageCounter) {}

    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     */
    #[Override]
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $response = parent::handle($request, $context);
        $name = $request->get('name');
        $usageMetric = is_string($name) ? UsageMetric::forTool($name) : null;

        if (! $usageMetric instanceof UsageMetric) {
            return $response;
        }

        if ($response instanceof JsonRpcResponse) {
            if ($this->worked($response)) {
                $this->count($usageMetric);
            }

            return $response;
        }

        return $this->countAfter($response, $usageMetric);
    }

    /**
     * A streamed call is counted once its last answer worked.
     *
     * @param  Generator<JsonRpcResponse>  $responses
     * @return Generator<JsonRpcResponse>
     */
    private function countAfter(Generator $responses, UsageMetric $usageMetric): Generator
    {
        $last = null;

        foreach ($responses as $response) {
            $last = $response;

            yield $response;
        }

        if ($last instanceof JsonRpcResponse && $this->worked($last)) {
            $this->count($usageMetric);
        }
    }

    private function worked(JsonRpcResponse $jsonRpcResponse): bool
    {
        $result = $jsonRpcResponse->content['result'] ?? null;

        return is_array($result) && ($result['isError'] ?? false) !== true;
    }

    private function count(UsageMetric $usageMetric): void
    {
        try {
            $this->usageCounter->add($usageMetric->value, now()->toDateTimeImmutable());
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
