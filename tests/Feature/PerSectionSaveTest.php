<?php

use App\Filament\Pages\Settings\MailSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage_mail_settings', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('manage_mail_settings');

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('admin');
    $this->actingAs($user);

    SiteSetting::set('mail_driver',             'log');
    SiteSetting::set('mail_from_name',          'Original Name');
    SiteSetting::set('mail_from_address',       'original@example.org');
    SiteSetting::set('mailchimp_server_prefix', 'us1');
    SiteSetting::set('mailchimp_audience_id',   'original-audience');
    SiteSetting::set('mailchimp_webhook_path',  'original-webhook');
});

it('saving one section does not overwrite other sections', function () {
    Livewire::test(MailSettingsPage::class)
        ->fillForm([
            'mail_driver'             => 'log',
            'mail_from_name'          => 'Edited Name',
            'mail_from_address'       => 'edited@example.org',
            'mailchimp_server_prefix' => 'us14',
            'mailchimp_audience_id'   => 'edited-audience',
            'mailchimp_webhook_path'  => 'edited-webhook',
        ])
        ->call('saveSection', 'sending', 'Sending');

    expect(SiteSetting::get('mail_from_name'))->toBe('Edited Name')
        ->and(SiteSetting::get('mail_from_address'))->toBe('edited@example.org')
        ->and(SiteSetting::get('mailchimp_server_prefix'))->toBe('us1')
        ->and(SiteSetting::get('mailchimp_audience_id'))->toBe('original-audience')
        ->and(SiteSetting::get('mailchimp_webhook_path'))->toBe('original-webhook');
});

it('saving the mailchimp section leaves the sending section untouched', function () {
    Livewire::test(MailSettingsPage::class)
        ->fillForm([
            'mail_driver'             => 'log',
            'mail_from_name'          => 'New Name',
            'mail_from_address'       => 'new@example.org',
            'mailchimp_server_prefix' => 'us21',
            'mailchimp_audience_id'   => 'mc-edited',
            'mailchimp_webhook_path'  => 'mc-edited-webhook',
        ])
        ->call('saveSection', 'mailchimp', 'MailChimp Configuration');

    expect(SiteSetting::get('mailchimp_server_prefix'))->toBe('us21')
        ->and(SiteSetting::get('mailchimp_audience_id'))->toBe('mc-edited')
        ->and(SiteSetting::get('mailchimp_webhook_path'))->toBe('mc-edited-webhook')
        ->and(SiteSetting::get('mail_from_name'))->toBe('Original Name')
        ->and(SiteSetting::get('mail_from_address'))->toBe('original@example.org');
});
