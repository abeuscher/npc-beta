<?php

use App\Models\EmailTemplate;
use App\Plugins\EmailTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 389 (arc D3): semantics of the core email-template registry
// (contract surface 7). The registry itself is core code; the Donations
// plugin's contribution is exercised by its own twin/mirror tests.

it('resolves a contributed handle through defaults()', function () {
    app(EmailTemplateRegistry::class)->contribute('probe_handle', [
        'subject' => 'Probe subject',
        'body'    => '<p>Probe body</p>',
    ]);

    $template = EmailTemplate::forHandle('probe_handle');

    expect($template->subject)->toBe('Probe subject')
        ->and($template->body)->toBe('<p>Probe body</p>');
});

it('gives the core defaults array precedence over a registry contribution', function () {
    app(EmailTemplateRegistry::class)->contribute('portal_password_reset', [
        'subject' => 'Shadowed subject',
        'body'    => '<p>Shadowed</p>',
    ]);

    expect(EmailTemplate::forHandle('portal_password_reset')->subject)
        ->toBe('Reset your password');
});

it('falls back to the empty template for a handle nobody owns', function () {
    $template = EmailTemplate::forHandle('nobody_owns_this_handle');

    expect($template->subject)->toBe('')
        ->and($template->body)->toBe('');
});

it('never overwrites an admin-edited row — the registry only supplies first-creation defaults', function () {
    app(EmailTemplateRegistry::class)->contribute('probe_edited', [
        'subject' => 'Registry subject',
        'body'    => '<p>Registry body</p>',
    ]);

    EmailTemplate::forHandle('probe_edited')->update(['subject' => 'Admin-edited subject']);

    expect(EmailTemplate::forHandle('probe_edited')->subject)->toBe('Admin-edited subject');
});

it('exposes every contribution for the seeder', function () {
    $registry = app(EmailTemplateRegistry::class);
    $registry->contribute('probe_a', ['subject' => 'A', 'body' => 'a']);
    $registry->contribute('probe_b', ['subject' => 'B', 'body' => 'b']);

    expect($registry->all())->toHaveKey('probe_a')
        ->and($registry->all())->toHaveKey('probe_b');
});
