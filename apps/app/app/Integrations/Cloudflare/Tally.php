<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare;

use App\Domain\Traffic\TrafficReport;

/**
 * Adds counts up by label, and ranks them, most first. Labels that differ only in case count as one, under the
 * spelling seen first.
 *
 * @phpstan-import-type Ranked from TrafficReport
 */
final class Tally
{
    /** @var array<string, array{label: string, count: int}> */
    private array $counts = [];

    public function add(string $label, int $count): void
    {
        if ($label === '' || $count <= 0) {
            return;
        }

        $key = mb_strtolower($label);
        $this->counts[$key] ??= ['label' => $label, 'count' => 0];
        $this->counts[$key]['count'] += $count;
    }

    /**
     * @return list<Ranked>
     */
    public function ranked(int $limit = PHP_INT_MAX): array
    {
        $ranked = array_values($this->counts);
        usort($ranked, static fn (array $a, array $b): int => [$b['count'], $a['label']] <=> [$a['count'], $b['label']]);

        return array_slice($ranked, 0, $limit);
    }
}
