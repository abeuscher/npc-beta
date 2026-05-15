<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormSubmissionMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Form $form,
        public FormSubmission $submission,
        public array $notification,
    ) {}

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forHandle('form_submission');

        return new Envelope(
            subject: $template->renderSubject($this->subjectTokens()),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forHandle('form_submission');
        $body     = $template->render($this->bodyTokens());
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

    private function subjectTokens(): array
    {
        return [
            'form_title' => str_replace(["\r", "\n"], ' ', (string) $this->form->title),
        ];
    }

    private function bodyTokens(): array
    {
        return [
            'form_title' => e($this->form->title),
            'submission' => $this->submissionTableHtml(),
        ];
    }

    private function submissionTableHtml(): string
    {
        if (! ($this->notification['include_submission_data'] ?? true)) {
            return '';
        }

        $data = $this->submission->data ?? [];
        $rows = '';

        foreach ($this->form->fields ?? [] as $field) {
            $handle = $field['handle'] ?? null;

            if (! $handle || ! array_key_exists($handle, $data)) {
                continue;
            }

            $value = $data[$handle];
            $value = is_array($value) ? implode(', ', $value) : (string) $value;

            $rows .= '<tr>'
                . '<td style="padding: 8px 12px; border: 1px solid #e0e0e0; background: #f9fafb; font-weight: bold; vertical-align: top; width: 35%;">' . e($field['label'] ?? $handle) . '</td>'
                . '<td style="padding: 8px 12px; border: 1px solid #e0e0e0; white-space: pre-wrap; word-break: break-word;">' . e($value) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            return '<p style="color: #888888;">This submission contained no field data.</p>';
        }

        return '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: collapse; margin: 16px 0;">' . $rows . '</table>';
    }
}
