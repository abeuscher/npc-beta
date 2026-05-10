<?php

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sanitises dashboard_welcome on set', function () {
    SiteSetting::set('dashboard_welcome', '<p>Welcome.</p><script>alert(1)</script>');

    expect(SiteSetting::get('dashboard_welcome'))->toBe('<p>Welcome.</p>');
});

it('sanitises system_page_content_reset_password on set', function () {
    SiteSetting::set('system_page_content_reset_password', '<p onclick="alert(1)">Reset</p>');

    expect(SiteSetting::get('system_page_content_reset_password'))->toBe('<p>Reset</p>');
});

it('sanitises system_page_content_email_verify on set', function () {
    SiteSetting::set('system_page_content_email_verify', '<a href="javascript:bad">Verify</a>');

    expect(SiteSetting::get('system_page_content_email_verify'))->toBe('<a>Verify</a>');
});

it('does not sanitise non-rich-text keys', function () {
    // Plain-text settings (e.g. an admin email field) should pass through
    // unchanged, including HTML-shaped characters that have no rich-text role.
    SiteSetting::set('admin_email', 'admin@example.com');

    expect(SiteSetting::get('admin_email'))->toBe('admin@example.com');
});
