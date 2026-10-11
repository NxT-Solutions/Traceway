<?php

declare(strict_types=1);

namespace App\Mail;

use App\Account\Email;
use App\Account\RiderName;
use App\Contracts\Mail\AccountMailer;
use Illuminate\Support\Facades\Mail;

final readonly class TracewayAccountMailer implements AccountMailer
{
    public function welcome(RiderName $riderName, Email $email): void
    {
        $this->send($email, 'welcome', 'Your Traceway account is ready', $riderName, url('/dashboard'));
    }

    public function passwordChanged(RiderName $riderName, Email $email): void
    {
        $this->send($email, 'password-changed', 'Your Traceway password changed', $riderName, route('password.request'));
    }

    public function emailChanged(RiderName $riderName, Email $previous, Email $current): void
    {
        $this->deliver($previous, new AccountMessage(
            template: 'email-changed',
            subjectLine: 'Your Traceway email changed',
            values: [
                'name' => $riderName->value,
                'email' => $previous->value,
                'new_email' => $current->value,
                'url' => route('password.request'),
                'home' => url('/'),
            ],
        ));
    }

    public function accountDeleted(RiderName $riderName, Email $email): void
    {
        $this->send($email, 'account-deleted', 'Your Traceway account is deleted', $riderName, url('/'));
    }

    public function dataExport(RiderName $riderName, Email $email, array $document): void
    {
        $this->deliver($email, new AccountMessage(
            template: 'data-export',
            subjectLine: 'Your Traceway data',
            values: [
                'name' => $riderName->value,
                'email' => $email->value,
                'url' => route('privacy.edit'),
                'home' => url('/'),
            ],
            attachment: $document,
        ));
    }

    private function send(Email $email, string $template, string $subject, RiderName $riderName, string $url): void
    {
        $this->deliver($email, new AccountMessage(
            template: $template,
            subjectLine: $subject,
            values: [
                'name' => $riderName->value,
                'email' => $email->value,
                'url' => $url,
                'home' => url('/'),
            ],
        ));
    }

    private function deliver(Email $email, AccountMessage $accountMessage): void
    {
        Mail::to($email->value)->send($accountMessage->afterCommit());
    }
}
