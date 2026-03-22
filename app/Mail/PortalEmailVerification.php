<?php

namespace App\Mail;

use App\Models\PortalAccount;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class PortalEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;

    public function __construct(public PortalAccount $account)
    {
        $this->verificationUrl = URL::temporarySignedRoute(
            'portal.verification.verify',
            now()->addMinutes(60),
            ['id' => $account->id, 'hash' => sha1($account->email)],
        );
    }

    public function envelope(): Envelope
    {
        $siteName = SiteSetting::get('site_name', config('app.name'));

        return new Envelope(
            subject: 'Verify your email address — ' . $siteName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal-email-verification',
            text: 'emails.portal-email-verification-text',
            with: [
                'account'         => $this->account,
                'verificationUrl' => $this->verificationUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
