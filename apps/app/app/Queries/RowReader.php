<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\Routing\Details\RouteDetails;
use App\Routes\SavedLeg;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * One query-builder row. Columns come back as mixed, so each read names a type.
 */
final readonly class RowReader
{
    public function __construct(private object $row) {}

    public function string(string $key): string
    {
        $value = $this->value($key);

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new RuntimeException('Column '.$key.' is not text.');
    }

    public function int(string $key): int
    {
        $value = $this->value($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        throw new RuntimeException('Column '.$key.' is not a number.');
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        return $this->string($key);
    }

    public function iso(string $key): ?string
    {
        $value = $this->optionalString($key);

        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->toIso8601String();
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public function line(string $key): array
    {
        return JsonColumn::line($this->value($key));
    }

    /**
     * @return list<array{0: float, 1: float}>|null
     */
    public function optionalLine(string $key): ?array
    {
        return JsonColumn::optionalLine($this->value($key));
    }

    /**
     * @return list<SavedLeg>
     */
    public function legs(string $key): array
    {
        return JsonColumn::legs($this->value($key));
    }

    public function details(string $key): ?RouteDetails
    {
        return JsonColumn::details($this->value($key));
    }

    public function mixed(string $key): mixed
    {
        return $this->value($key);
    }

    private function value(string $key): mixed
    {
        $values = get_object_vars($this->row);

        throw_unless(array_key_exists($key, $values), RuntimeException::class, 'Column '.$key.' is missing.');

        return $values[$key];
    }
}
