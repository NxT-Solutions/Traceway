<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare\Insights;

use App\Domain\Traffic\BackupHealth;
use App\Domain\Traffic\SecurityReport;
use App\Integrations\Cloudflare\CloudflareGraphql;
use DateTimeImmutable;

/**
 * The size of the backup bucket over the last day, and its writes each day. Litestream writes a file each time the
 * database changes, and a snapshot every day.
 *
 * @phpstan-import-type DayCount from SecurityReport
 */
final readonly class BackupsReader
{
    private const string QUERY = <<<'GRAPHQL'
        query ($accountTag: string, $storage: AccountR2StorageAdaptiveGroupsFilter_InputObject, $writes: AccountR2OperationsAdaptiveGroupsFilter_InputObject) {
          viewer {
            accounts(filter: {accountTag: $accountTag}) {
              storage: r2StorageAdaptiveGroups(limit: 1, filter: $storage) {
                max { objectCount payloadSize metadataSize }
              }
              writes: r2OperationsAdaptiveGroups(limit: 100, filter: $writes) {
                sum { requests }
                dimensions { date }
              }
            }
          }
        }
        GRAPHQL;

    /** The R2 operations that put a file in the bucket. */
    private const array WRITES = ['PutObject', 'CompleteMultipartUpload', 'CopyObject'];

    public function __construct(
        private CloudflareGraphql $cloudflareGraphql,
        private string $accountId,
        private string $bucket,
    ) {}

    public function read(DateTimeImmutable $now): BackupHealth
    {
        $account = $this->cloudflareGraphql->ask(self::QUERY, [
            'accountTag' => $this->accountId,
            'storage' => [
                'bucketName' => $this->bucket,
                'datetime_geq' => CloudflareGraphql::time($now->modify('-1 day')),
                'datetime_leq' => CloudflareGraphql::time($now),
            ],
            'writes' => [
                'bucketName' => $this->bucket,
                'actionType_in' => self::WRITES,
                'datetime_geq' => CloudflareGraphql::time($now->setTime(0, 0)->modify(sprintf('-%d days', BackupHealth::DAYS - 1))),
                'datetime_leq' => CloudflareGraphql::time($now),
            ],
        ], 'accounts');

        $storage = CloudflareGraphql::rows($account, 'storage')[0] ?? [];
        $writes = [];

        foreach (CloudflareGraphql::rows($account, 'writes') as $row) {
            $date = CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'date'));

            if ($date !== '') {
                $writes[$date] = ($writes[$date] ?? 0) + CloudflareGraphql::int(CloudflareGraphql::value($row, 'sum', 'requests'));
            }
        }

        ksort($writes);
        $written = array_keys(array_filter($writes, static fn (int $count): bool => $count > 0));

        return new BackupHealth(
            bucket: $this->bucket,
            objects: CloudflareGraphql::int(CloudflareGraphql::value($storage, 'max', 'objectCount')),
            bytes: CloudflareGraphql::int(CloudflareGraphql::value($storage, 'max', 'payloadSize')) + CloudflareGraphql::int(CloudflareGraphql::value($storage, 'max', 'metadataSize')),
            writeDays: array_map(static fn (string $date, int $count): array => ['date' => $date, 'count' => $count], array_keys($writes), array_values($writes)),
            lastWrite: $written === [] ? null : end($written),
        );
    }
}
