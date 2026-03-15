<?php

namespace App\Mail;

use App\Models\EventDate;
use App\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EventRegistration $registration,
        public EventDate $eventDate,
    ) {
        $this->registration->loadMissing('event');
        $this->eventDate->loadMissing('event');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: ' . $this->registration->event->title . ' is coming up',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.event-reminder',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
