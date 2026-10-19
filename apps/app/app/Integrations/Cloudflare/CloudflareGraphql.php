<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare;

use App\Domain\Traffic\TrafficUnavailable;
use App\Integrations\Cloudflare\Requests\AnalyticsQueryRequest;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Saloon\Exceptions\Request\FatalRequestException;

/**
 * Asks the Analytics API one GraphQL question and reads its loosely typed answer. A refusal, an error page, or an
 * answer that is not JSON all become TrafficUnavailable.
 */
final readonly class CloudflareGraphql
{
    public function __construct(private CloudflareAnalyticsConnector $cloudflareAnalyticsConnector) {}

    /**
     * The first zone or account of the answer, such as `zones` or `accounts`.
     *
     * @param  array<string, mixed>  $variables
     * @return array<mixed>
     */
    public function ask(string $query, array $variables, string $scope): array
    {
        try {
            $response = $this->cloudflareAnalyticsConnector->send(new AnalyticsQueryRequest($query, $variables));
        } catch (FatalRequestException $fatalRequestException) {
            throw new TrafficUnavailable('Cloudflare did not answer.', 0, $fatalRequestException);
        }

        throw_if($response->failed(), TrafficUnavailable::class, 'Cloudflare refused the question. Check the token can read analytics.');

        try {
            $body = $response->json();
        } catch (JsonException $jsonException) {
            throw new TrafficUnavailable('Cloudflare answered with something other than numbers.', 0, $jsonException);
        }

        throw_if(is_array($body['errors'] ?? null) && $body['errors'] !== [], TrafficUnavailable::class, 'Cloudflare refused the question. Check the token can read analytics.');

        $viewer = is_array($body['data'] ?? null) ? ($body['data']['viewer'] ?? null) : null;
        $list = is_array($viewer) ? ($viewer[$scope] ?? null) : null;
        $first = is_array($list) ? ($list[0] ?? null) : null;

        return is_array($first) ? $first : [];
    }

    /**
     * The rows of one alias in an answer.
     *
     * @param  array<mixed>  $node
     * @return list<array<mixed>>
     */
    public static function rows(array $node, string $alias): array
    {
        $rows = $node[$alias] ?? null;

        return is_array($rows) ? array_values(array_filter($rows, is_array(...))) : [];
    }

    /**
     * A value one level down in a row, such as `dimensions.date` or `sum.requests`.
     *
     * @param  array<mixed>  $row
     */
    public static function value(array $row, string $outer, string $inner): mixed
    {
        return is_array($row[$outer] ?? null) ? ($row[$outer][$inner] ?? null) : null;
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    public static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * A moment as Cloudflare's filters take it.
     */
    public static function time(DateTimeImmutable $dateTimeImmutable): string
    {
        return $dateTimeImmutable->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * A calendar day in UTC, as Cloudflare's daily groups take it.
     */
    public static function date(DateTimeImmutable $dateTimeImmutable): string
    {
        return $dateTimeImmutable->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }
}
