<?php

declare(strict_types=1);

namespace App\Contracts\Status;

use DateTimeImmutable;

interface StatusRepository
{
    /**
     * Saves one run of checks at once, so a reader never sees half a run.
     *
     * @param  list<array{component: string, state: string, milliseconds: int}>  $checks
     */
    public function recordRun(array $checks, DateTimeImmutable $checkedAt): void;

    /**
     * Forgets the checks older than the day bars show.
     */
    public function forgetChecksBefore(DateTimeImmutable $before): int;

    /**
     * @param  list<string>  $components
     * @return int The new notice.
     */
    public function postNotice(string $title, ?string $body, array $components, DateTimeImmutable $startedAt): int;

    /**
     * Closes the notice, or the newest open one when no id is given.
     *
     * @return int|null The notice that was closed, or null when none was open.
     */
    public function resolveNotice(?int $noticeId, DateTimeImmutable $resolvedAt): ?int;
}
