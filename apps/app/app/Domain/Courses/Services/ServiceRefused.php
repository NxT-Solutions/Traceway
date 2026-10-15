<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

use RuntimeException;

/**
 * The service no longer accepts Traceway's token. The rider has to connect again.
 */
final class ServiceRefused extends RuntimeException {}
