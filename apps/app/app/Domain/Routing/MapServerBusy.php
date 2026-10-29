<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use RuntimeException;

/**
 * The map server did not answer at all: it could not be reached, it timed
 * out, or it turned every request away as busy. Nothing is wrong with the
 * rider's pin or points, and asking again in a minute works.
 */
final class MapServerBusy extends RuntimeException {}
