<?php

namespace App\Mail;

use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Per-gift tax acknowledgment for a single successful donation.
 *
 * Distinct from App\Mail\DonationReceipt, which is the annual, aggregated
 * year-end statement sent manually from the Giving Summary page. This one is
 * dispatched automatically from the Stripe webhook the moment a gift clears and
 * carries the IRS contemporaneous-acknowledgment language for that one gift.
 * It reuses the shared EmailTemplate render/wrapper machinery (handle
 * `donation_acknowledgment`), so the copy is admin-editable under System Emails.
 */
class DonationAcknowledgment extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contact $contact,
        public string $amount,
        public string $donationDate,
        public string $reference,
        public ?string $fundName = null,
    ) {}

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forHandle('donation_acknowledgment');

        return new Envelope(
            subject: $template->renderSubject($this->tokens()),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forHandle('donation_acknowledgment');
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
            'contact_name' => $this->contact->display_name,
            'org_name'     => SiteSetting::get('site_name', ''),
            'amount'       => $this->amount,
            'date'         => $this->donationDate,
            'fund'         => $this->fundName ?? 'General Fund',
            'reference'    => $this->reference,
        ];
    }
}
