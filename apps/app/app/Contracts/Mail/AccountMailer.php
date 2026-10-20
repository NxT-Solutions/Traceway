<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

use App\Account\ConnectionProvider;
use App\Account\Email;
use App\Account\RiderName;
use SensitiveParameter;

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
     * Tells the rider a service can now log in to their account, so one they did not connect is noticed.
     */
    public function connectionAdded(RiderName $riderName, Email $email, ConnectionProvider $connectionProvider): void;

    public function connectionRemoved(RiderName $riderName, Email $email, ConnectionProvider $connectionProvider): void;

    /**
     * Sends a new rider who came from a service the link that opens their account, which proves the address is
     * theirs. Nothing is opened until they use it.
     */
    public function connectionSignUp(RiderName $riderName, Email $email, ConnectionProvider $connectionProvider, #[SensitiveParameter] string $token): void;

    /**
     * @param  array<string, mixed>  $document
     */
    public function dataExport(RiderName $riderName, Email $email, array $document): void;
}
