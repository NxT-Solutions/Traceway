<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

use RuntimeException;

/**
 * The service could not be reached, or it turned the course down. Trying again later may work.
 */
final class ServiceUnavailable extends RuntimeException {}
