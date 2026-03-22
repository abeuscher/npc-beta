<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\PortalAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PortalAccount $account,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forHandle('portal_password_reset');

        return new Envelope(
            subject: $template->renderSubject($this->tokens()),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forHandle('portal_password_reset');
        $body     = $template->render($this->tokens());
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
        $resetUrl = route('portal.password.reset', ['token' => $this->token])
            . '?email=' . urlencode($this->account->email);

        return [
            'first_name' => $this->account->contact?->first_name ?? $this->account->email,
            'reset_url'  => $resetUrl,
        ];
    }
}
