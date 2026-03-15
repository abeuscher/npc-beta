<?php

namespace App\Mail;

use App\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    // Production note: QUEUE_CONNECTION=redis is active. This mailable sends
    // synchronously via Mail::to()->send(). To queue it instead, implement
    // ShouldQueue and switch callers to Mail::to()->queue().

    public function __construct(public EventRegistration $registration)
    {
        $this->registration->loadMissing('event.eventDates');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'re registered: ' . $this->registration->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.registration-confirmation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
