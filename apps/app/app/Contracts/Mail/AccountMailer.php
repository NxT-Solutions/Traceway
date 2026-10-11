<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

use App\Account\Email;
use App\Account\RiderName;

interface AccountMailer
{
    public function welcome(RiderName $riderName, Email $email): void;

    public function passwordChanged(RiderName $riderName, Email $email): void;

    /**
     * Tells the previous address that the sign-in email changed.
     */
    public function emailChanged(RiderName $riderName, Email $previous, Email $current): void;

    public function accountDeleted(RiderName $riderName, Email $email): void;

    /**
     * @param  array<string, mixed>  $document
     */
    public function dataExport(RiderName $riderName, Email $email, array $document): void;
}
