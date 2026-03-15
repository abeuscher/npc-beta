<?php

namespace App\Mail;

use App\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventCancellation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EventRegistration $registration)
    {
        $this->registration->loadMissing('event');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Event cancelled: ' . $this->registration->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.event-cancellation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
