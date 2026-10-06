<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Pest 5. Feature tests boot the Laravel application. Unit tests that only
| exercise domain values stay on PHPUnit's case so they do not open a
| database. Architecture tests do the same.
|
| RefreshDatabase is applied per file, not globally, so a test that does not
| touch the database does not pay for a migration.
*/

pest()->extend(TestCase::class)->in('Feature');
