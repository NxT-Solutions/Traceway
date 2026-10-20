<?php

declare(strict_types=1);

namespace App\Auth\Connections;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

/**
 * Apple posts its answer from appleid.apple.com, and the browser leaves Traceway's Lax session cookie off a post
 * from another site. So the answer waits here, under a one-use ticket that lives two minutes, while the browser
 * comes back with a plain link that does carry the session. The code and the rider's name never sit in a URL.
 */
final readonly class AppleCallbackTicket
{
    private const int SECONDS = 120;

    /**
     * The fields Apple posts, and the longest each can be. Apple's are far shorter, so anything longer did not
     * come from Apple.
     */
    private const array FIELDS = ['code' => 512, 'state' => 128, 'user' => 2048, 'error' => 64];

    public function __construct(private Repository $repository) {}

    /**
     * Keeps the answer and gives the ticket for it, or null when a field is not one Apple would send. Then
     * nothing is kept.
     *
     * @param  array<mixed>  $answer
     */
    public function hold(array $answer): ?string
    {
        $kept = [];

        foreach (self::FIELDS as $field => $longest) {
            $value = $answer[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_string($value) || strlen($value) > $longest) {
                return null;
            }

            $kept[$field] = $value;
        }

        $ticket = Str::random(48);

        $this->repository->put($this->key($ticket), $kept, self::SECONDS);

        return $ticket;
    }

    /**
     * The answer for the ticket, once, or null when it was used or ran out.
     *
     * @return array<string, string>|null
     */
    public function redeem(string $ticket): ?array
    {
        if (preg_match('/^[A-Za-z0-9]{48}$/', $ticket) !== 1) {
            return null;
        }

        $answer = $this->repository->pull($this->key($ticket));

        if (! is_array($answer)) {
            return null;
        }

        return array_filter($answer, static fn (mixed $value, mixed $field): bool => is_string($field) && is_string($value), ARRAY_FILTER_USE_BOTH);
    }

    private function key(string $ticket): string
    {
        return 'connections:apple:'.hash('sha256', $ticket);
    }
}
