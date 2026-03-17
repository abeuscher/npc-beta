<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test');
    }

    public function content(): Content
    {
        return new Content(text: 'test');
    }

    public function attachments(): array
    {
        return [];
    }
}
