<?php

use App\Mail\FormSubmissionMailable;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope as MailerEnvelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function notifyForm(array $notifications, array $settingsOverrides = []): Form
{
    return Form::create([
        'title'    => 'Contact',
        'handle'   => 'notify-form',
        'fields'   => [
            ['type' => 'text',     'label' => 'Name',    'handle' => 'name',    'required' => true,  'validation' => 'none'],
            ['type' => 'email',    'label' => 'Email',   'handle' => 'email',   'required' => false, 'validation' => 'email'],
            ['type' => 'textarea', 'label' => 'Message', 'handle' => 'message', 'required' => false, 'validation' => 'none'],
        ],
        'settings' => array_merge([
            'honeypot'        => true,
            'form_type'       => 'general',
            'success_message' => 'Thank you.',
            'notifications'   => $notifications,
        ], $settingsOverrides),
        'is_active' => true,
    ]);
}

function submit(Form $form, array $data = []): \Illuminate\Testing\TestResponse
{
    return test()->post(route('forms.submit', $form->handle), array_merge([
        'name'    => 'Alice Smith',
        'email'   => 'alice@example.com',
        'message' => 'Hello there.',
        '_hp'     => '',
    ], $data));
}

function renderNotification(Form $form, FormSubmission $submission, ?array $notification = null): string
{
    return (new FormSubmissionMailable($form, $submission, $notification ?? $form->settings['notifications'][0]))->render();
}

function notificationSubject(Form $form, FormSubmission $submission, ?array $notification = null): string
{
    return (new FormSubmissionMailable($form, $submission, $notification ?? $form->settings['notifications'][0]))->envelope()->subject;
}

// ── Schema round-trip ─────────────────────────────────────────────────────────

it('round-trips the notifications array on Form.settings', function () {
    $config = [['to' => '{{contact_email}}', 'include_submission_data' => true]];

    $form = notifyForm($config);

    expect($form->fresh()->settings['notifications'])->toBe($config);
});

// ── Dispatch ──────────────────────────────────────────────────────────────────

it('dispatches one notification to a literal recipient on submit', function () {
    Mail::fake();

    $form = notifyForm([['to' => 'owner@site.test']]);

    submit($form);

    Mail::assertSent(FormSubmissionMailable::class, 1);
    Mail::assertSent(FormSubmissionMailable::class, fn ($mail) => $mail->hasTo('owner@site.test'));
});

it('resolves the {{contact_email}} token from the CMS contact_email SiteSetting', function () {
    Mail::fake();
    SiteSetting::set('contact_email', 'configured-owner@site.test');

    $form = notifyForm([['to' => '{{contact_email}}']]);

    submit($form);

    Mail::assertSent(FormSubmissionMailable::class, fn ($mail) => $mail->hasTo('configured-owner@site.test'));
});

it('dispatches a distinct notification per array entry', function () {
    Mail::fake();

    $form = notifyForm([
        ['to' => 'one@site.test'],
        ['to' => 'two@site.test'],
    ]);

    submit($form);

    Mail::assertSent(FormSubmissionMailable::class, 2);
    Mail::assertSent(FormSubmissionMailable::class, fn ($mail) => $mail->hasTo('one@site.test'));
    Mail::assertSent(FormSubmissionMailable::class, fn ($mail) => $mail->hasTo('two@site.test'));
});

it('sends nothing when the notifications key is absent (backward-compat)', function () {
    Mail::fake();

    $form = notifyForm([]);
    $form->update(['settings' => ['honeypot' => true, 'form_type' => 'general', 'success_message' => 'Thank you.']]);

    submit($form->fresh());

    Mail::assertNothingSent();
});

it('does not send when the resolved recipient is empty or malformed', function () {
    Mail::fake();

    $form = notifyForm([
        ['to' => ''],
        ['to' => 'not-an-email'],
        ['to' => '   '],
    ]);

    submit($form);

    Mail::assertNothingSent();
});

// ── Security control: recipient invariant ─────────────────────────────────────

it('never lets submission data redirect, add, or CRLF-inject a recipient or header', function () {
    Mail::fake();

    $form = notifyForm([['to' => 'owner@site.test']]);

    submit($form, [
        'name'    => "Mallory\r\nBcc: leak@evil.test",
        'email'   => 'alice@example.com',
        'message' => "attacker@evil.test\r\nCc: another@evil.test\r\nBcc: third@evil.test\nReply-To: spoof@evil.test",
    ]);

    Mail::assertSent(FormSubmissionMailable::class, 1);
    Mail::assertSent(FormSubmissionMailable::class, function ($mail) {
        return $mail->hasTo('owner@site.test')
            && count($mail->to) === 1
            && $mail->cc === []
            && $mail->bcc === []
            && ! $mail->hasTo('attacker@evil.test')
            && ! $mail->hasTo('leak@evil.test');
    });
});

// ── Security control: token resolver is an allowlist ──────────────────────────

it('rejects unknown or malformed recipient tokens without sending or leaking SiteSetting values', function () {
    Mail::fake();
    SiteSetting::set('resend_api_key', 'secret-should-never-be-a-recipient@leak.test');
    SiteSetting::set('contact_email', '');

    $form = notifyForm([
        ['to' => '{{resend_api_key}}'],
        ['to' => '{{ }}'],
        ['to' => '{{contact_email}}'],
        ['to' => '{{nope}}'],
    ]);

    submit($form);

    Mail::assertNothingSent();
});

// ── Security control: submission data is HTML-escaped in the rendered email ────

it('HTML-escapes submitted field values in the rendered notification', function () {
    $form = notifyForm([['to' => 'owner@site.test']]);

    $submission = FormSubmission::create([
        'form_id'    => $form->id,
        'data'       => [
            'name'    => '<script>alert(1)</script>',
            'message' => '"><img src=x onerror=alert(2)>',
        ],
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $html = renderNotification($form, $submission);

    expect($html)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(2)>');
});

// ── Security control: submission data can never reach the subject header ───────

it('keeps submission data out of the subject even if the template subject misuses {{submission}}', function () {
    $template = EmailTemplate::forHandle('form_submission');
    $template->update(['subject' => 'Tampered {{submission}} for {{form_title}}']);

    $form = notifyForm([['to' => 'owner@site.test']]);

    $submission = FormSubmission::create([
        'form_id'    => $form->id,
        'data'       => ['name' => 'INJECTED-SUBMISSION-VALUE', 'message' => 'secret body'],
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $subject = notificationSubject($form, $submission);

    expect($subject)
        ->toContain('{{submission}}')
        ->toContain('Contact')
        ->not->toContain('INJECTED-SUBMISSION-VALUE')
        ->not->toContain('secret body');
});

// ── Default editable template content ─────────────────────────────────────────

it('renders the form title and submitted field values from the default system-email template', function () {
    $form = notifyForm([['to' => 'owner@site.test']]);

    $submission = FormSubmission::create([
        'form_id'    => $form->id,
        'data'       => ['name' => 'Alice Smith', 'message' => 'Hello there.'],
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $html    = renderNotification($form, $submission);
    $subject = notificationSubject($form, $submission);

    expect($subject)->toBe('New submission: Contact');
    expect($html)
        ->toContain('A new submission was received')
        ->toContain('Contact')
        ->toContain('Alice Smith')
        ->toContain('Hello there.')
        ->toContain('Name')
        ->toContain('Message');
});

it('omits the field table when include_submission_data is false', function () {
    $form = notifyForm([['to' => 'owner@site.test', 'include_submission_data' => false]]);

    $submission = FormSubmission::create([
        'form_id'    => $form->id,
        'data'       => ['name' => 'Alice Smith', 'message' => 'Secret message.'],
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    expect(renderNotification($form, $submission))->not->toContain('Secret message.');
});

// ── Editability via the System Emails admin surface ───────────────────────────

it('reflects operator edits to the form_submission system email in the sent notification', function () {
    $template = EmailTemplate::forHandle('form_submission');
    $template->update([
        'subject' => 'Heads up — {{form_title}} was filled out',
        'body'    => '<p>Custom operator wording for {{form_title}}.</p>{{submission}}',
    ]);

    $form = notifyForm([['to' => 'owner@site.test']]);

    $submission = FormSubmission::create([
        'form_id'    => $form->id,
        'data'       => ['name' => 'Alice Smith'],
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    expect(notificationSubject($form, $submission))->toBe('Heads up — Contact was filled out');
    expect(renderNotification($form, $submission))
        ->toContain('Custom operator wording for Contact.')
        ->toContain('Alice Smith');
});

it('ignores a legacy per-form subject key now that the subject lives on the system email', function () {
    Mail::fake();

    $form = notifyForm([['to' => 'owner@site.test', 'subject' => 'Legacy ignored subject']]);

    submit($form);

    Mail::assertSent(FormSubmissionMailable::class, function ($mail) {
        return $mail->envelope()->subject === 'New submission: Contact';
    });
});

// ── Graceful handling when the mail transport fails (bad API key etc.) ─────────

it('degrades gracefully and does not leak when the mail transport throws on send', function () {
    $throwing = new class implements TransportInterface {
        public function send(RawMessage $message, ?MailerEnvelope $envelope = null): ?SentMessage
        {
            throw new TransportException('Simulated transport failure (e.g. bad API key)');
        }

        public function __toString(): string
        {
            return 'throwing';
        }
    };

    config([
        'mail.default'          => 'throwing',
        'mail.mailers.throwing' => ['transport' => 'throwing'],
    ]);
    Mail::extend('throwing', fn () => $throwing);

    SiteSetting::set('contact_email', 'owner@site.test');
    $form = notifyForm([['to' => '{{contact_email}}']]);

    $response = submit($form);

    // Page is not broken: no 500 / exception page, just the form's normal error path.
    $response->assertSessionHasErrors('_form');
    expect($response->getStatusCode())->toBe(302);

    // The visitor sees a generic server-error message — no transport detail leaks.
    $error = session('errors')->get('_form')[0];
    expect($error)
        ->toContain('server error')
        ->not->toContain('Simulated transport failure')
        ->not->toContain('API key');

    // The submission itself is still stored (data not lost).
    expect(FormSubmission::where('form_id', $form->id)->count())->toBe(1);
});
