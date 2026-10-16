<?php

declare(strict_types=1);

namespace App\Contracts\Status;

use DateTimeImmutable;

interface StatusRepository
{
    public function recordCheck(string $component, string $state, int $milliseconds, DateTimeImmutable $checkedAt): void;

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
