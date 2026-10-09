<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

use App\Account\Email;
use App\Account\RiderName;

interface AccountMailer
{
    public function welcome(RiderName $riderName, Email $email): void;

    public function passwordChanged(RiderName $riderName, Email $email): void;

    public function accountDeleted(RiderName $riderName, Email $email): void;

    /**
     * @param  array<string, mixed>  $document
     */
    public function dataExport(RiderName $riderName, Email $email, array $document): void;
}
