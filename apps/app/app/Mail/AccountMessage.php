<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountMessage extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 15];

    /**
     * @param  array<string, string>  $values
     * @param  array<string, mixed>|null  $attachment
     */
    public function __construct(
        public string $template,
        public string $subjectLine,
        public array $values,
        public ?array $attachment = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.frame',
            with: [
                'html' => TracewayMail::html($this->template, $this->values),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->attachment === null) {
            return [];
        }

        $payload = $this->attachment;

        return [
            Attachment::fromData(
                fn (): string => (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'traceway-data.json',
            )->withMime('application/json'),
        ];
    }
}
