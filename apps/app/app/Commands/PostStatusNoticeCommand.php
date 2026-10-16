<?php

declare(strict_types=1);

namespace App\Commands;

use App\Domain\Status\StatusComponent;

/**
 * Tells riders, on the status page and on the map, about a problem in plain words.
 */
final readonly class PostStatusNoticeCommand
{
    /**
     * @param  list<StatusComponent>  $components  the parts it is about, if any
     */
    public function __construct(
        public string $title,
        public ?string $body = null,
        public array $components = [],
    ) {}
}
