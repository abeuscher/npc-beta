<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmailTemplate extends Model
{
    protected $fillable = [
        'handle',
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
        ];

        return $defaults[$handle] ?? ['subject' => '', 'body' => ''];
    }
}
