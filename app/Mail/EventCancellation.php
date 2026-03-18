<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\EventRegistration;
use App\Models\SiteSetting;
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
        $this->registration->loadMissing('event', 'contact');
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forHandle('event_cancellation');
        $tokens   = $this->tokens();

        return new Envelope(
            subject: $template->renderSubject($tokens),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forHandle('event_cancellation');
        $tokens   = $this->tokens();
        $body     = $template->render($tokens);
        $html     = $template->resolveWrapper($body);

        return new Content(
            view: 'mail.system-email',
            with: ['html' => $html],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function tokens(): array
    {
        $reg   = $this->registration;
        $event = $reg->event;

        return [
            'first_name'  => $reg->contact?->first_name ?? $reg->name ?? '',
            'last_name'   => $reg->contact?->last_name ?? '',
            'event_title' => $event->title ?? '',
            'site_name'   => SiteSetting::get('site_name', ''),
        ];
    }
}
