<?php

namespace App\Mail;

use App\Models\PortalAccount;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalEmailChange extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PortalAccount $account,
        public string $newEmail,
        public string $confirmUrl,
    ) {}

    public function envelope(): Envelope
    {
        $siteName = SiteSetting::get('site_name', config('app.name'));

        return new Envelope(
            subject: 'Confirm your new email address — ' . $siteName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal-email-change',
            with: [
                'account'    => $this->account,
                'newEmail'   => $this->newEmail,
                'confirmUrl' => $this->confirmUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
