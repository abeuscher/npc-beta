<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmailTemplate extends Model
{
    protected $fillable = [
        'handle',     // system-managed: template identifier, set by seeder (e.g. registration_confirmation)
        'subject',
        'body',
        'header_color',
        'header_image_path',
        'header_text',
        'footer_sender_name',
        'footer_reply_to',
        'footer_address',
        'footer_reason',
        'custom_template_path',
    ];

    public static function forHandle(string $handle): self
    {
        return static::firstOrCreate(
            ['handle' => $handle],
            static::defaults($handle)
        );
    }

    public function replaceTokens(string $text, array $tokens): string
    {
        foreach ($tokens as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) $value, $text);
        }
        return $text;
    }

    public function renderSubject(array $tokens): string
    {
        return $this->replaceTokens($this->subject, $tokens);
    }

    public function render(array $tokens): string
    {
        return $this->replaceTokens($this->body, $tokens);
    }

    public function resolveWrapper(string $renderedBody): string
    {
        if ($this->custom_template_path) {
            $html = Storage::disk('public')->get($this->custom_template_path);
            return str_replace('{{content}}', $renderedBody, $html);
        }

        return view('mail.default-wrapper', [
            'template' => $this,
            'body'     => $renderedBody,
        ])->render();
    }

    private static function defaults(string $handle): array
    {
        $defaults = [
            'registration_confirmation' => [
                'subject'        => 'You\'re registered: {{event_title}}',
                'body'           => '<p>Hi {{first_name}},</p><p>You are registered for <strong>{{event_title}}</strong>.</p>',
                'footer_reason'  => 'You received this email because you registered for {{event_title}}.',
            ],
            'event_cancellation' => [
                'subject'        => 'Cancelled: {{event_title}}',
                'body'           => '<p>Hi {{first_name}},</p><p>We\'re sorry to let you know that <strong>{{event_title}}</strong> has been cancelled.</p>',
                'footer_reason'  => 'You received this email because you were registered for {{event_title}}.',
            ],
            'event_reminder' => [
                'subject'        => 'Reminder: {{event_title}} is coming up',
                'body'           => '<p>Hi {{first_name}},</p><p>This is a reminder that <strong>{{event_title}}</strong> is coming up on {{event_date}}.</p>',
                'footer_reason'  => 'You received this email because you registered for {{event_title}}.',
            ],
            'portal_password_reset' => [
                'subject' => 'Reset your password',
                'body'    => '<p>Hi {{first_name}},</p><p>Click the link below to reset your password. This link expires in 60 minutes.</p><p><a href="{{reset_url}}">Reset password</a></p><p>If you did not request a password reset, you can safely ignore this email.</p>',
            ],
            'admin_invitation' => [
                'subject'       => 'You\'ve been invited to {{org_name}}',
                'body'          => '<p>Hi {{name}},</p><p>You have been invited to access the {{org_name}} admin panel. Click the link below to set your password and activate your account.</p><p><a href="{{invitation_url}}">Set your password</a></p><p>This link expires in 48 hours. If you were not expecting this invitation, you can safely ignore this email.</p>',
                'footer_reason' => 'You received this email because someone invited you to access the admin panel.',
            ],
            'donation_receipt' => [
                'subject'       => 'Your {{tax_year}} donation receipt — {{org_name}}',
                'body'          => '<p>Dear {{contact_name}},</p><p>Thank you for your generous support of {{org_name}} in {{tax_year}}. This letter serves as your official donation receipt for the {{tax_year}} tax year.</p>{{donations}}<p><strong>Total donations: ${{total}}</strong></p><p>No goods or services were provided in exchange for these contributions. Please retain this letter for your tax records.</p><p>With gratitude,<br>{{org_name}}</p>',
                'footer_reason' => 'You received this email because you made a donation to {{org_name}} in {{tax_year}}.',
            ],
        ];

        return $defaults[$handle] ?? ['subject' => '', 'body' => ''];
    }
}
