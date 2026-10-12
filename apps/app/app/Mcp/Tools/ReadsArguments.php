<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;

/**
 * Typed reads of validated tool arguments, and the person behind the token.
 */
trait ReadsArguments
{
    /**
     * @param  array<array-key, mixed>  $arguments
     */
    private function text(array $arguments, string $key): string
    {
        $value = $arguments[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    private function number(array $arguments, string $key): float
    {
        $value = $arguments[$key] ?? 0;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function riderId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
