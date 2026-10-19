<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * Keeps the Web Analytics totals of each finished UTC day while Cloudflare still answers it in full, and forgets
 * kept days older than a season.
 */
final readonly class RecordTrafficSnapshotCommand {}
