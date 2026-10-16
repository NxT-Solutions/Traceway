<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * Closes a notice once the problem is over. With no id, it closes the newest open one.
 */
final readonly class ResolveStatusNoticeCommand
{
    public function __construct(public ?int $noticeId = null) {}
}
