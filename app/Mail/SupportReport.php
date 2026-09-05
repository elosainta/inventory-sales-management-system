<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string  $senderName,
        public readonly string  $senderEmail,
        public readonly string  $senderRole,
        public readonly string  $description,
        public readonly ?string $mediaContent  = null,
        public readonly ?string $mediaFilename = null,
        public readonly ?string $mediaMime     = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[ISMS Support] ' . $this->senderName . ' reported a problem',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-report');
    }

    public function attachments(): array
    {
        if ($this->mediaContent !== null) {
            return [
                Attachment::fromData(
                    fn () => $this->mediaContent,
                    $this->mediaFilename
                )->withMime($this->mediaMime),
            ];
        }

        return [];
    }
}
