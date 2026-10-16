<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\PostStatusNoticeCommand;
use App\Contracts\Status\StatusRepository;
use App\Domain\Status\StatusComponent;
use InvalidArgumentException;

final readonly class PostStatusNoticeHandler
{
    public function __construct(private StatusRepository $statusRepository) {}

    /**
     * @return int The new notice.
     */
    public function handle(PostStatusNoticeCommand $postStatusNoticeCommand): int
    {
        $title = trim($postStatusNoticeCommand->title);
        $body = $postStatusNoticeCommand->body === null ? null : trim($postStatusNoticeCommand->body);

        throw_if($title === '' || mb_strlen($title) > 120, InvalidArgumentException::class, 'A notice needs a title of 1 to 120 characters.');

        return $this->statusRepository->postNotice(
            $title,
            $body === '' ? null : $body,
            array_values(array_unique(array_map(fn (StatusComponent $statusComponent): string => $statusComponent->value, $postStatusNoticeCommand->components))),
            now()->toDateTimeImmutable(),
        );
    }
}
