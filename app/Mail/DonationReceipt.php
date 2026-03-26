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

class DonationReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contact $contact,
        public int $taxYear,
        public array $breakdown,
        public string $total,
    ) {}

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forHandle('donation_receipt');
        $tokens   = $this->tokens();

        return new Envelope(
            subject: $template->renderSubject($tokens),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forHandle('donation_receipt');
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
        $orgName = SiteSetting::get('site_name', '');

        $donationsHtml = '<table style="width:100%;border-collapse:collapse;margin:1em 0;">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ccc;">Fund</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ccc;">Restriction</th>'
            . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ccc;">Amount</th>'
            . '</tr></thead><tbody>';

        foreach ($this->breakdown as $line) {
            $donationsHtml .= '<tr>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;">' . htmlspecialchars($line['fund_label']) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;">' . htmlspecialchars($line['restriction_type']) . '</td>'
                . '<td style="text-align:right;padding:6px 8px;border-bottom:1px solid #eee;">$' . number_format((float) $line['amount'], 2) . '</td>'
                . '</tr>';
        }

        $donationsHtml .= '</tbody></table>';

        return [
            'contact_name' => $this->contact->display_name,
            'org_name'     => $orgName,
            'tax_year'     => (string) $this->taxYear,
            'donations'    => $donationsHtml,
            'total'        => number_format((float) $this->total, 2),
        ];
    }
}
