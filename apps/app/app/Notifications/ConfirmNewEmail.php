<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\TracewayMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The link that makes a new email count, sent to that email. It is signed without the host, and the address it
 * leads to is built from APP_URL, so a forged Host header cannot send it elsewhere.
 */
class ConfirmNewEmail extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 15];

    public function __construct(
        public readonly int $riderId,
        public readonly string $name,
        public readonly string $email,
    ) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $values = [
            'name' => $this->name,
            'email' => $this->email,
            'url' => $this->url(),
            'home' => rtrim(config()->string('app.url'), '/').'/',
        ];

        return (new MailMessage)
            ->subject('Confirm your new Traceway email')
            ->view(['mail.frame', 'mail.text'], [
                'html' => TracewayMail::html('confirm-new-email', $values),
                'text' => TracewayMail::text('confirm-new-email', $values),
            ]);
    }

    public function url(): string
    {
        $minutes = config('auth.verification.expire', 60);

        return rtrim(config()->string('app.url'), '/').URL::temporarySignedRoute(
            'profile.email.confirm',
            now()->addMinutes(is_numeric($minutes) ? (int) $minutes : 60),
            ['id' => $this->riderId, 'hash' => sha1($this->email)],
            absolute: false,
        );
    }
}
