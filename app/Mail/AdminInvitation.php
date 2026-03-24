<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forHandle('admin_invitation');

        return new Envelope(
            subject: $template->renderSubject($this->tokens()),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forHandle('admin_invitation');
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
        return [
            'name'           => $this->user->name,
            'org_name'       => config('app.name'),
            'invitation_url' => url('/admin/invitation/' . $this->plainToken),
        ];
    }
}
