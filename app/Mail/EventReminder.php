<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SiteSetting;
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
        public Event $event,
    ) {
        $this->registration->loadMissing('contact');
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forHandle('event_reminder');
        $tokens   = $this->tokens();

        return new Envelope(
            subject: $template->renderSubject($tokens),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forHandle('event_reminder');
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
        $reg = $this->registration;

        return [
            'first_name'     => $reg->contact?->first_name ?? $reg->name ?? '',
            'last_name'      => $reg->contact?->last_name ?? '',
            'event_title'    => $this->event->title ?? '',
            'event_date'     => $this->event->starts_at?->format('F j, Y') ?? '',
            'event_location' => $this->event->location ?? '',
            'site_name'      => SiteSetting::get('site_name', ''),
        ];
    }
}
