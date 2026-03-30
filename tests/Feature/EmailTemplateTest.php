<?php

use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a template via forHandle', function () {
    $template = EmailTemplate::forHandle('test_welcome');

    expect($template->exists)->toBeTrue()
        ->and($template->handle)->toBe('test_welcome');
});

it('forHandle returns the same record on subsequent calls', function () {
    $first = EmailTemplate::forHandle('registration_confirmation');
    $second = EmailTemplate::forHandle('registration_confirmation');

    expect($first->id)->toBe($second->id)
        ->and(EmailTemplate::where('handle', 'registration_confirmation')->count())->toBe(1);
});

it('replaces tokens in subject and body', function () {
    $template = EmailTemplate::forHandle('test_tokens');
    $template->update([
        'subject' => 'Welcome, {{name}}!',
        'body'    => '<p>Hello {{name}}, your event is {{event_title}}.</p>',
    ]);

    $subject = $template->renderSubject(['name' => 'Jane']);
    $body = $template->render(['name' => 'Jane', 'event_title' => 'Gala 2026']);

    expect($subject)->toBe('Welcome, Jane!')
        ->and($body)->toContain('Hello Jane')
        ->and($body)->toContain('Gala 2026');
});

it('leaves unknown tokens unreplaced', function () {
    $template = EmailTemplate::forHandle('test_unknown');
    $template->update(['subject' => 'Hello {{unknown_token}}']);

    expect($template->renderSubject([]))->toBe('Hello {{unknown_token}}');
});

it('enforces unique handle', function () {
    EmailTemplate::forHandle('unique_test');

    expect(fn () => EmailTemplate::create(['handle' => 'unique_test', 'subject' => 'Dup', 'body' => '']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
