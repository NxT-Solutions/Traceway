<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use DateInterval;
use DateTimeImmutable;

/**
 * A finished UTC day that Cloudflare answered with nothing at all: no page loads, no page, no referrer, and no
 * country. One empty answer could be Cloudflare having a bad hour, so the day is kept with no page loads only after
 * it came back empty several times, hours apart. Until then it is asked again.
 */
final readonly class EmptyDay
{
    /** How many empty answers make a day with no page loads. */
    public const int ANSWERS = 3;

    /** How far apart two empty answers are, at the least, to count twice. */
    public const int SPACING_HOURS = 6;

    /**
     * @param  string  $date  The UTC day, as Y-m-d.
     * @param  DateTimeImmutable  $lastCountedAt  When the last empty answer that counted came.
     */
    public function __construct(
        public string $date,
        public int $answers,
        public DateTimeImmutable $lastCountedAt,
    ) {}

    public static function first(string $date, DateTimeImmutable $at): self
    {
        return new self($date, 1, $at);
    }

    /**
     * The day after one more empty answer. An answer sooner than the spacing after the last one does not count.
     */
    public function answeredAgain(DateTimeImmutable $at): self
    {
        if ($at < $this->lastCountedAt->add(new DateInterval('PT'.self::SPACING_HOURS.'H'))) {
            return $this;
        }

        return new self($this->date, $this->answers + 1, $at);
    }

    /**
     * Whether the day came back empty often enough to keep it with no page loads.
     */
    public function settled(): bool
    {
        return $this->answers >= self::ANSWERS;
    }

    public function trafficDay(): TrafficDay
    {
        return new TrafficDay($this->date, 0, 0, [], [], []);
    }
}
