<?php

use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sanitises EmailTemplate.body on save', function () {
    $template = EmailTemplate::create([
        'handle'  => 'test_template',
        'subject' => 'Test',
        'body'    => '<p>Hi {{first_name}},</p><script>alert(1)</script><p onclick="alert(2)">x</p>',
    ]);

    expect($template->fresh()->body)
        ->toBe('<p>Hi {{first_name}},</p><p>x</p>');
});

it('preserves token-bearing href on EmailTemplate.body', function () {
    $template = EmailTemplate::create([
        'handle'  => 'test_link',
        'subject' => 'Test',
        'body'    => '<p>Click <a href="{{reset_url}}">here</a>.</p>',
    ]);

    expect($template->fresh()->body)
        ->toBe('<p>Click <a href="{{reset_url}}">here</a>.</p>');
});
