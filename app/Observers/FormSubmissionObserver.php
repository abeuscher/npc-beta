<?php

namespace App\Observers;

use App\Exceptions\FormNotificationDeliveryException;
use App\Mail\FormSubmissionMailable;
use App\Models\FormSubmission;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Mail;

class FormSubmissionObserver
{
    private const ALLOWED_TOKENS = ['contact_email'];

    public function created(FormSubmission $submission): void
    {
        $form = $submission->form;

        if (! $form) {
            return;
        }

        $notifications = $form->settings['notifications'] ?? [];

        if (! is_array($notifications)) {
            return;
        }

        foreach ($notifications as $notification) {
            if (! is_array($notification)) {
                continue;
            }

            $recipient = $this->resolveRecipient($notification['to'] ?? '');

            if ($recipient === null) {
                continue;
            }

            try {
                Mail::to($recipient)->send(new FormSubmissionMailable($form, $submission, $notification));
            } catch (\Throwable $e) {
                report($e);

                throw new FormNotificationDeliveryException('Form notification delivery failed.', previous: $e);
            }
        }
    }

    private function resolveRecipient(mixed $to): ?string
    {
        if (! is_string($to)) {
            return null;
        }

        $to = trim($to);

        if (preg_match('/^\{\{\s*(\w+)\s*\}\}$/', $to, $m)) {
            if (! in_array($m[1], self::ALLOWED_TOKENS, true)) {
                return null;
            }

            $to = trim((string) SiteSetting::get($m[1], ''));
        }

        if ($to === '' || strpbrk($to, "\r\n,") !== false) {
            return null;
        }

        return filter_var($to, FILTER_VALIDATE_EMAIL) ?: null;
    }
}
