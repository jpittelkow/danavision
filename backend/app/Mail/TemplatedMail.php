<?php

namespace App\Mail;

use App\Services\RenderedEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RenderedEmail $rendered
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->rendered->subject
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->rendered->html
        );
    }
}
