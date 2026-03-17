<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;

class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test');
    }

    public function build(): static
    {
        return $this->text('mail.test');
    }

    public function attachments(): array
    {
        return [];
    }
}
