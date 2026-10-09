<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\TracewayMail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail as FortifyVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use RuntimeException;

class VerifyEmail extends FortifyVerifyEmail implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 15];

    public function toMail(mixed $notifiable): MailMessage
    {
        throw_unless($notifiable instanceof User, RuntimeException::class, 'An email confirmation is sent to a rider.');

        return (new MailMessage)
            ->subject('Confirm your Traceway email')
            ->view('mail.frame', [
                'html' => TracewayMail::html('verify-email', [
                    'name' => $notifiable->name,
                    'email' => $notifiable->email,
                    'url' => $this->verificationUrl($notifiable),
                    'home' => url('/'),
                ]),
            ]);
    }
}
