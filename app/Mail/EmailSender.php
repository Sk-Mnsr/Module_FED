<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailSender extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, mixed> */
    private array $templateData;

    public function __construct(
        private string $mailSubject,
        private string $templateView,
        array $data = [],
    ) {
        $this->templateData = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: $this->templateView,
            with: ['data' => $this->templateData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
