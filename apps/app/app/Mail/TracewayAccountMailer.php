<?php

declare(strict_types=1);

namespace App\Mail;

use App\Account\ConnectionProvider;
use App\Account\Email;
use App\Account\RiderName;
use App\Contracts\Mail\AccountMailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use SensitiveParameter;

/**
 * Every link in these emails is on Traceway's own address from APP_URL. Some are built while a visitor's request is
 * answered, and a host from that request could be theirs, so a link that carries a secret would lead to their site.
 */
final readonly class TracewayAccountMailer implements AccountMailer
{
    public function welcome(RiderName $riderName, Email $email): void
    {
        $this->send($email, 'welcome', 'Your Traceway account is ready', $riderName, $this->link('/dashboard'));
    }

    public function passwordChanged(RiderName $riderName, Email $email): void
    {
        $this->send($email, 'password-changed', 'Your Traceway password changed', $riderName, $this->route('password.request'));
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
                // The old address cannot reset a password any more, so it is pointed at help instead.
                'url' => $this->link('/support'),
                'home' => $this->link('/'),
            ],
        ));
    }

    public function accountDeleted(RiderName $riderName, Email $email): void
    {
        $this->send($email, 'account-deleted', 'Your Traceway account is deleted', $riderName, $this->link('/'));
    }

    public function connectionAdded(RiderName $riderName, Email $email, ConnectionProvider $connectionProvider): void
    {
        $this->connection($email, 'connection-added', 'You can now log in to Traceway with '.$connectionProvider->label(), $riderName, $connectionProvider);
    }

    public function connectionRemoved(RiderName $riderName, Email $email, ConnectionProvider $connectionProvider): void
    {
        $this->connection($email, 'connection-removed', $connectionProvider->label().' is no longer connected to Traceway', $riderName, $connectionProvider);
    }

    public function connectionSignUp(RiderName $riderName, Email $email, ConnectionProvider $connectionProvider, #[SensitiveParameter] string $token): void
    {
        $this->deliver($email, new AccountMessage(
            template: 'connection-sign-up',
            subjectLine: 'Finish your Traceway account',
            values: [
                'name' => $riderName->value,
                'email' => $email->value,
                'service' => $connectionProvider->label(),
                'url' => $this->route('connections.finish.link', ['token' => $token]),
                'home' => $this->link('/'),
            ],
        ));
    }

    public function dataExport(RiderName $riderName, Email $email, array $document): void
    {
        $this->deliver($email, new AccountMessage(
            template: 'data-export',
            subjectLine: 'Your Traceway data',
            values: [
                'name' => $riderName->value,
                'email' => $email->value,
                'url' => $this->route('privacy.edit'),
                'home' => $this->link('/'),
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
                'home' => $this->link('/'),
            ],
        ));
    }

    private function connection(Email $email, string $template, string $subject, RiderName $riderName, ConnectionProvider $connectionProvider): void
    {
        $this->deliver($email, new AccountMessage(
            template: $template,
            subjectLine: $subject,
            values: [
                'name' => $riderName->value,
                'email' => $email->value,
                'service' => $connectionProvider->label(),
                'url' => $this->route('connections.edit'),
                'reset' => $this->route('password.request'),
                'home' => $this->link('/'),
            ],
        ));
    }

    /**
     * A path on Traceway's own address.
     */
    private function link(string $path): string
    {
        $root = rtrim(Config::string('app.url'), '/');
        $path = trim($path, '/');

        return $path === '' ? $root : $root.'/'.$path;
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function route(string $name, array $parameters = []): string
    {
        return $this->link(route($name, $parameters, absolute: false));
    }

    private function deliver(Email $email, AccountMessage $accountMessage): void
    {
        Mail::to($email->value)->send($accountMessage->afterCommit());
    }
}
