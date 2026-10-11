<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\TracewayMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as FortifyResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use RuntimeException;

class ResetPassword extends FortifyResetPassword implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 15];

    public function toMail(mixed $notifiable): MailMessage
    {
        throw_unless($notifiable instanceof User, RuntimeException::class, 'A password reset is sent to a rider.');

        $values = [
            'name' => $notifiable->name,
            'email' => $notifiable->email,
            'url' => $this->resetUrl($notifiable),
            'home' => url('/'),
        ];

        return (new MailMessage)
            ->subject('Reset your Traceway password')
            ->view(['mail.frame', 'mail.text'], [
                'html' => TracewayMail::html('reset-password', $values),
                'text' => TracewayMail::text('reset-password', $values),
            ]);
    }
}
