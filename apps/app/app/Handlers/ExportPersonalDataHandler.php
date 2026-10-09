<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\ExportPersonalDataCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Mail\AccountMailer;
use App\Queries\Account\PersonalDataQuery;

final readonly class ExportPersonalDataHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private PersonalDataQuery $personalDataQuery,
        private AccountMailer $accountMailer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(ExportPersonalDataCommand $exportPersonalDataCommand): array
    {
        $this->currentRider->assertSame($exportPersonalDataCommand->riderId);

        $document = $this->personalDataQuery->document($exportPersonalDataCommand->riderId->value);
        $payload = $document->toArray();
        $this->accountMailer->dataExport($document->name, $document->email, $payload);

        return $payload;
    }
}
