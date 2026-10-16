<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\ResolveStatusNoticeCommand;
use App\Contracts\Status\StatusRepository;

final readonly class ResolveStatusNoticeHandler
{
    public function __construct(private StatusRepository $statusRepository) {}

    /**
     * @return int|null The notice that was closed, or null when none was open.
     */
    public function handle(ResolveStatusNoticeCommand $resolveStatusNoticeCommand): ?int
    {
        return $this->statusRepository->resolveNotice($resolveStatusNoticeCommand->noticeId, now()->toDateTimeImmutable());
    }
}
