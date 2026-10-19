<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use RuntimeException;

/**
 * The traffic totals could not be read, because the service did not answer or refused the key.
 */
final class TrafficUnavailable extends RuntimeException {}
